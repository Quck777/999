<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Session;

class AuctionController extends BaseController
{
    private const AUCTION_HOUSE_FEE = 0.05; // 5% fee
    private const AUCTION_DURATION_HOURS = 24;

    /**
     * GET /api/auction/list - Return active auctions
     */
    public function list(array $params): void
    {
        $charId = $this->requireCharacter();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, (int)($_GET['per_page'] ?? 20));
        $offset = ($page - 1) * $perPage;

        $type = $_GET['type'] ?? '';
        $rarity = $_GET['rarity'] ?? '';
        $search = $_GET['search'] ?? '';

        $sql = "SELECT a.id, a.item_id, a.seller_character_id, a.starting_price, a.current_bid,
                       a.current_bidder_id, a.buyout_price, a.ends_at, a.created_at,
                       i.name, i.type, i.rarity, i.icon, i.stats, i.level_requirement, i.description,
                       c.name as seller_name
                FROM auctions a
                JOIN items i ON i.id = a.item_id
                JOIN characters c ON c.id = a.seller_character_id
                WHERE a.status = 'active' AND a.ends_at > NOW()";
        $sqlParams = [];

        if (!empty($type)) {
            $sql .= " AND i.type = ?";
            $sqlParams[] = $type;
        }

        if (!empty($rarity)) {
            $sql .= " AND i.rarity = ?";
            $sqlParams[] = $rarity;
        }

        if (!empty($search)) {
            $sql .= " AND i.name LIKE ?";
            $sqlParams[] = '%' . $search . '%';
        }

        $countSql = str_replace(
            "SELECT a.id, a.item_id, a.seller_character_id, a.starting_price, a.current_bid,
                    a.current_bidder_id, a.buyout_price, a.ends_at, a.created_at,
                    i.name, i.type, i.rarity, i.icon, i.stats, i.level_requirement, i.description,
                    c.name as seller_name",
            "SELECT COUNT(*) as cnt",
            $sql
        );
        $total = $this->db->queryOne($countSql, $sqlParams);

        $sql .= " ORDER BY a.ends_at ASC LIMIT ? OFFSET ?";
        $sqlParams[] = $perPage;
        $sqlParams[] = $offset;

        $auctions = $this->db->query($sql, $sqlParams);

        // Get highest bids for current user
        $myBids = [];
        if (!empty($auctions)) {
            $auctionIds = array_column($auctions, 'id');
            $placeholders = implode(',', array_fill(0, count($auctionIds), '?'));
            $myBidsRaw = $this->db->query(
                "SELECT auction_id, MAX(bid_amount) as my_max_bid
                 FROM auction_bids
                 WHERE auction_id IN ({$placeholders}) AND bidder_character_id = ?
                 GROUP BY auction_id",
                [...$auctionIds, $charId]
            );
            foreach ($myBidsRaw as $bid) {
                $myBids[(int)$bid['auction_id']] = (int)$bid['my_max_bid'];
            }
        }

        $this->jsonSuccess([
            'auctions'   => $auctions,
            'my_bids'    => $myBids,
            'total'      => (int)$total['cnt'],
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => (int)ceil((int)$total['cnt'] / $perPage),
        ]);
    }

    /**
     * POST /api/auction/create - List item for auction
     */
    public function create(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $inventoryId = (int)($input['inventory_id'] ?? 0);
        $startingPrice = (int)($input['starting_price'] ?? 0);
        $buyoutPrice = (int)($input['buyout_price'] ?? 0);
        $durationHours = min(72, max(1, (int)($input['duration_hours'] ?? self::AUCTION_DURATION_HOURS)));

        if ($inventoryId <= 0) {
            $this->jsonError('Укажите предмет для выставления');
        }

        if ($startingPrice <= 0) {
            $this->jsonError('Начальная цена должна быть больше 0');
        }

        if ($buyoutPrice > 0 && $buyoutPrice < $startingPrice) {
            $this->jsonError('Цена выкупа не может быть меньше начальной');
        }

        // Get inventory item
        $invItem = $this->db->queryOne(
            "SELECT inv.id, inv.item_id, inv.quantity, inv.is_equipped, i.name
             FROM inventory inv
             JOIN items i ON i.id = inv.item_id
             WHERE inv.id = ? AND inv.character_id = ?",
            [$inventoryId, $charId]
        );

        if (!$invItem) {
            $this->jsonError('Предмет не найден в инвентаре');
        }

        if ((bool)$invItem['is_equipped']) {
            $this->jsonError('Сначала снимите предмет перед продажей');
        }

        // Calculate listing fee
        $fee = (int)ceil($startingPrice * self::AUCTION_HOUSE_FEE);

        $char = $this->db->queryOne(
            "SELECT gold FROM characters WHERE id = ?",
            [$charId]
        );

        if ((int)$char['gold'] < $fee) {
            $this->jsonError("Недостаточно золота для оплаты комиссии ({$fee} золота)");
        }

        $endsAt = date('Y-m-d H:i:s', time() + ($durationHours * 3600));

        try {
            $this->db->beginTransaction();

            // Deduct listing fee
            $this->db->execute(
                "UPDATE characters SET gold = gold - ? WHERE id = ? AND gold >= ?",
                [$fee, $charId, $fee]
            );

            // Remove item from inventory
            if ((int)$invItem['quantity'] > 1) {
                $this->db->execute(
                    "UPDATE inventory SET quantity = quantity - 1 WHERE id = ?",
                    [$inventoryId]
                );
            } else {
                $this->db->delete('inventory', 'id = ?', [$inventoryId]);
            }

            // Create auction
            $auctionId = $this->db->insert('auctions', [
                'item_id'               => (int)$invItem['item_id'],
                'seller_character_id'   => $charId,
                'starting_price'        => $startingPrice,
                'current_bid'           => $startingPrice,
                'current_bidder_id'     => null,
                'buyout_price'          => $buyoutPrice > 0 ? $buyoutPrice : null,
                'ends_at'               => $endsAt,
                'status'                => 'active',
                'created_at'            => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            $this->jsonSuccess([
                'auction_id' => $auctionId,
                'fee'        => $fee,
            ], "Предмет «{$invItem['name']}» выставлен на аукцион!");
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при создании аукциона');
        }
    }

    /**
     * POST /api/auction/bid - Place a bid on auction
     */
    public function bid(array $params): void
    {
        $charId = $this->requireCharacter();
        $this->requireCsrf();

        $input = getInput();
        $auctionId = (int)($input['auction_id'] ?? 0);
        $bidAmount = (int)($input['bid_amount'] ?? 0);

        if ($auctionId <= 0) {
            $this->jsonError('Укажите аукцион');
        }

        if ($bidAmount <= 0) {
            $this->jsonError('Укажите сумму ставки');
        }

        // Get auction
        $auction = $this->db->queryOne(
            "SELECT a.*, i.name as item_name
             FROM auctions a
             JOIN items i ON i.id = a.item_id
             WHERE a.id = ? AND a.status = 'active' AND a.ends_at > NOW()",
            [$auctionId]
        );

        if (!$auction) {
            $this->jsonError('Аукцион не найден, завершён или недоступен');
        }

        // Cannot bid on own auction
        if ((int)$auction['seller_character_id'] === $charId) {
            $this->jsonError('Нельзя делать ставки на свои лоты');
        }

        // Bid must be higher than current bid
        $currentBid = (int)$auction['current_bid'];
        $minBid = (int)ceil($currentBid * 1.05); // 5% minimum increment

        if ($bidAmount < $minBid) {
            $this->jsonError("Минимальная ставка: {$minBid} золота");
        }

        // Check buyout
        $buyoutPrice = (int)($auction['buyout_price'] ?? 0);
        if ($buyoutPrice > 0 && $bidAmount >= $buyoutPrice) {
            $bidAmount = $buyoutPrice;
        }

        // Check gold
        $char = $this->db->queryOne(
            "SELECT gold FROM characters WHERE id = ?",
            [$charId]
        );

        if ((int)$char['gold'] < $bidAmount) {
            $this->jsonError('Недостаточно золота');
        }

        try {
            $this->db->beginTransaction();

            // Place the bid
            $this->db->insert('auction_bids', [
                'auction_id'          => $auctionId,
                'bidder_character_id' => $charId,
                'bid_amount'          => $bidAmount,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);

            // Update auction current bid
            $this->db->update('auctions', [
                'current_bid'       => $bidAmount,
                'current_bidder_id' => $charId,
            ], 'id = ? AND status = ?', [$auctionId, 'active']);

            // If buyout price reached, close auction immediately
            if ($buyoutPrice > 0 && $bidAmount >= $buyoutPrice) {
                $this->finalizeAuction($auctionId, $charId, $bidAmount);
            }

            $this->db->commit();

            if ($buyoutPrice > 0 && $bidAmount >= $buyoutPrice) {
                $this->jsonSuccess([
                    'auction_id' => $auctionId,
                    'bid_amount' => $bidAmount,
                    'won'        => true,
                ], "Вы выкупили «{$auction['item_name']}» за {$bidAmount} золота!");
            } else {
                $this->jsonSuccess([
                    'auction_id' => $auctionId,
                    'bid_amount' => $bidAmount,
                ], "Ставка {$bidAmount} золота принята!");
            }
        } catch (\Throwable $e) {
            $this->db->rollback();
            $this->jsonError('Ошибка при создании ставки');
        }
    }

    /**
     * Finalize an auction: transfer item to winner, gold to seller (minus fee)
     */
    private function finalizeAuction(int $auctionId, int $winnerId, int $winningBid): void
    {
        $auction = $this->db->queryOne(
            "SELECT * FROM auctions WHERE id = ? AND status = 'active'",
            [$auctionId]
        );

        if (!$auction) {
            return;
        }

        $sellerId = (int)$auction['seller_character_id'];
        $itemId = (int)$auction['item_id'];

        // Mark auction as completed
        $this->db->update('auctions', [
            'status'     => 'completed',
            'winner_id'  => $winnerId,
            'final_price'=> $winningBid,
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$auctionId]);

        // Refund previous bidders (return gold to outbid players)
        $previousBidders = $this->db->query(
            "SELECT bidder_character_id, MAX(bid_amount) as max_bid
             FROM auction_bids
             WHERE auction_id = ? AND bidder_character_id != ?
             GROUP BY bidder_character_id",
            [$auctionId, $winnerId]
        );

        foreach ($previousBidders as $bidder) {
            $this->db->execute(
                "UPDATE characters SET gold = gold + ? WHERE id = ?",
                [(int)$bidder['max_bid'], (int)$bidder['bidder_character_id']]
            );
        }

        // Deduct gold from winner
        $this->db->execute(
            "UPDATE characters SET gold = gold - ? WHERE id = ?",
            [$winningBid, $winnerId]
        );

        // Give gold to seller (minus 5% fee)
        $sellerFee = (int)ceil($winningBid * self::AUCTION_HOUSE_FEE);
        $sellerGold = $winningBid - $sellerFee;

        $this->db->execute(
            "UPDATE characters SET gold = gold + ? WHERE id = ?",
            [$sellerGold, $sellerId]
        );

        // Give item to winner
        $existing = $this->db->queryOne(
            "SELECT id, quantity FROM inventory WHERE character_id = ? AND item_id = ? AND is_equipped = FALSE",
            [$winnerId, $itemId]
        );

        if ($existing) {
            $this->db->execute(
                "UPDATE inventory SET quantity = quantity + 1 WHERE id = ?",
                [(int)$existing['id']]
            );
        } else {
            $this->db->insert('inventory', [
                'character_id' => $winnerId,
                'item_id'      => $itemId,
                'quantity'     => 1,
                'is_equipped'  => false,
                'obtained_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
