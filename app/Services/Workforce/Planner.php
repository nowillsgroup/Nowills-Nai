<?php
namespace App\Services\Workforce;

interface Planner
{
    /** @return array{plan: array, input_tokens: ?int, output_tokens: ?int} */
    public function plan(string $instruction): array;
}
