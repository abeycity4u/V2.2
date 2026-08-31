<?php
/**
 * V2.2.17 Attribution & Cost Centre Foundation.
 *
 * Production type answers "which operation owns this transaction?" while
 * cycle_id answers "which exact cycle?". A NULL cycle with a concrete
 * production type is intentionally valid for pooled/shared output such as eggs
 * combined from multiple active layer cycles.
 */
function attribution_production_types(string $farmType): array
{
    $farmType = strtolower(trim($farmType));
    if ($farmType === 'poultry') {
        return ['layer' => 'Layer', 'broiler' => 'Broiler', 'shared' => 'Shared Poultry / Other Poultry'];
    }
    if ($farmType === 'ruminant') {
        return ['cattle' => 'Cattle', 'goat' => 'Goat', 'sheep' => 'Sheep', 'other' => 'Other', 'shared' => 'Shared Ruminant / Other Ruminant'];
    }
    if ($farmType === 'general') {
        return ['general' => 'General / Other Farm Income'];
    }
    return [];
}

function attribution_normalize_production_type(string $farmType, ?string $productionType): string
{
    $farmType = strtolower(trim($farmType));
    $productionType = strtolower(trim((string)$productionType));
    $allowed = attribution_production_types($farmType);
    if (isset($allowed[$productionType])) return $productionType;
    if ($farmType === 'general') return 'general';
    return 'shared';
}

function attribution_scope(?int $cycleId, string $farmType, string $productionType): string
{
    if (($cycleId ?? 0) > 0) return 'cycle';
    if ($farmType === 'general' || $productionType === 'shared') return 'farm';
    return 'production_type';
}

function attribution_validate_cycle(PDO $pdo, int $farmId, ?int $cycleId, string $farmType, string $productionType): ?array
{
    if (($cycleId ?? 0) <= 0) return null;
    $stmt = $pdo->prepare("SELECT id, cycle_code, farm_type, production_type, status FROM production_cycles WHERE id=? AND farm_id=? LIMIT 1");
    $stmt->execute([(int)$cycleId, $farmId]);
    $cycle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cycle) throw new RuntimeException('The selected production cycle does not belong to this farm.');
    if (strtolower((string)$cycle['farm_type']) !== strtolower($farmType)) {
        throw new RuntimeException('The selected production cycle does not match the selected farm type.');
    }
    if (strtolower((string)$cycle['production_type']) !== strtolower($productionType)) {
        throw new RuntimeException('The selected production cycle does not match the selected production type.');
    }
    return $cycle;
}

function attribution_label(?string $productionType): string
{
    $value = strtolower(trim((string)$productionType));
    $labels = [
        'layer'=>'Layer','broiler'=>'Broiler','cattle'=>'Cattle','goat'=>'Goat',
        'sheep'=>'Sheep','other'=>'Other','shared'=>'Shared Operation','general'=>'General'
    ];
    return $labels[$value] ?? ($value !== '' ? ucfirst($value) : 'Unallocated');
}
