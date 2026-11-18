<?php
declare(strict_types=1);

namespace Wiz\Repositories;

use Wiz\Core\Database;

/**
 * Shared ordering behaviour for lists staff can rearrange.
 *
 * Ordering is an integer column rather than a drag-and-drop widget: the arrow
 * buttons are easier to use on a phone and impossible to drop in the wrong
 * place. Equality of sort_order does not break anything — the swap simply moves
 * one row past another.
 */
abstract class OrderedRepository
{
    public function __construct(protected readonly Database $db)
    {
    }

    /** Table this repository orders. Must return a literal, never user input. */
    abstract protected function table(): string;

    /**
     * Column that scopes the ordering, or null when the list is global.
     * Services are ordered inside their category; categories and gallery
     * items are ordered globally.
     */
    protected function scopeColumn(): ?string
    {
        return null;
    }

    /**
     * Move one row up or down by swapping sort_order with its neighbour.
     *
     * @param 'up'|'down' $direction
     */
    public function move(int $id, string $direction): bool
    {
        $table = $this->db->quoteIdentifier($this->table());
        $row = $this->db->first("SELECT id, sort_order, " . $this->scopeSelect() . " FROM {$table} WHERE id = :id", [':id' => $id]);

        if ($row === null) {
            return false;
        }

        $where = '';
        $params = [
            ':order' => (int) $row['sort_order'],
            ':id' => $id,
        ];

        $scopeColumn = $this->scopeColumn();

        if ($scopeColumn !== null) {
            $where = ' AND ' . $this->db->quoteIdentifier($scopeColumn) . ' <=> :scope';
            $params[':scope'] = $row[$scopeColumn];
        }

        $comparison = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';

        $neighbour = $this->db->first(
            "SELECT id, sort_order FROM {$table}
              WHERE sort_order {$comparison} :order{$where}
              ORDER BY sort_order {$order}
              LIMIT 1",
            array_diff_key($params, [':id' => true]),
        );

        if ($neighbour === null) {
            return false;
        }

        $this->db->transaction(function (Database $db) use ($table, $row, $neighbour): void {
            $db->execute("UPDATE {$table} SET sort_order = :order WHERE id = :id", [
                ':order' => (int) $neighbour['sort_order'],
                ':id' => (int) $row['id'],
            ]);

            $db->execute("UPDATE {$table} SET sort_order = :order WHERE id = :id", [
                ':order' => (int) $row['sort_order'],
                ':id' => (int) $neighbour['id'],
            ]);
        });

        return true;
    }

    /**
     * Next free position at the end of the list.
     */
    protected function nextSortOrder(): int
    {
        $table = $this->db->quoteIdentifier($this->table());
        $value = $this->db->scalar("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {$table}");

        return (int) $value;
    }

    private function scopeSelect(): string
    {
        $column = $this->scopeColumn();

        return $column === null ? 'NULL AS scope' : $this->db->quoteIdentifier($column);
    }
}
