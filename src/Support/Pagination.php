<?php

declare(strict_types=1);

final class Pagination
{
    public int $page;
    public int $perPage;
    public int $total;
    public int $totalPages;

    public function __construct(int $page, int $perPage, int $total)
    {
        $this->perPage = max(1, $perPage);
        $this->total = max(0, $total);
        $this->totalPages = (int)max(1, (int)ceil($this->total / $this->perPage));
        $this->page = min(max(1, $page), $this->totalPages);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function url(string $base, array $extra = [], int $page = 0): string
    {
        $page = $page > 0 ? $page : $this->page;
        $extra['page'] = $page;
        $qs = http_build_query($extra);
        $sep = (str_contains($base, '?')) ? '&' : '?';
        return $base . $sep . $qs;
    }
}
