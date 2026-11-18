<?php
declare(strict_types=1);

namespace Wiz\Support;

/**
 * A page of a larger list.
 *
 * The repositories do the counting and the slicing; this object holds the
 * arithmetic and the answers the templates need ("is there a next page?", "what
 * range is this?"). Keeping it in one place means every list in the panel
 * paginates the same way.
 */
final class Paginator
{
    public readonly int $page;

    public readonly int $perPage;

    public readonly int $total;

    public function __construct(int $page, int $perPage, int $total)
    {
        $this->perPage = max(1, $perPage);
        $this->total = max(0, $total);
        $this->page = min(max(1, $page), $this->pages());
    }

    public static function of(int $page, int $perPage, int $total): self
    {
        return new self($page, $perPage, $total);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages();
    }

    public function previousPage(): int
    {
        return max(1, $this->page - 1);
    }

    public function nextPage(): int
    {
        return min($this->pages(), $this->page + 1);
    }

    /** First row number on this page, 1-based. Zero when the list is empty. */
    public function firstItem(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    /** Last row number on this page, 1-based. */
    public function lastItem(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    /**
     * Page numbers to show, with null where a group is skipped.
     *
     * 1 … 4 [5] 6 … 20 — enough context to move without twenty links.
     *
     * @return list<int|null>
     */
    public function window(int $around = 1): array
    {
        $last = $this->pages();

        if ($last <= 7) {
            return range(1, $last);
        }

        $from = max(2, $this->page - $around);
        $to = min($last - 1, $this->page + $around);
        $out = [1];

        if ($from > 2) {
            $out[] = null;
        }

        for ($i = $from; $i <= $to; $i++) {
            $out[] = $i;
        }

        if ($to < $last - 1) {
            $out[] = null;
        }

        $out[] = $last;

        return $out;
    }
}
