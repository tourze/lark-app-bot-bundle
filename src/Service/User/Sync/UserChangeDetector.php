<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User\Sync;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 用户变更检测器.
 *
 * 负责检测用户数据的变更和差异
 */
#[Autoconfigure(public: true)]
class UserChangeDetector
{
    /**
     * 关键字段列表.
     */
    private const KEY_FIELDS = [
        'name',
        'en_name',
        'email',
        'mobile',
        'status',
        'department_ids',
        'leader_user_id',
        'is_tenant_manager',
    ];

    /**
     * 检测具体的变化.
     *
     * @param array<mixed> $oldData
     * @param array<mixed> $newData
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function detectChanges(array $oldData, array $newData): array
    {
        $changes = [];

        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;

            if ($this->valueHasChanged($oldValue, $newValue)) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * 检查是否应该触发更新事件.
     *
     * @param array<mixed>|null $oldData
     * @param array<mixed>      $newData
     */
    public function shouldDispatchUpdateEvent(?array $oldData, array $newData): bool
    {
        return null !== $oldData && $this->hasChanges($oldData, $newData);
    }

    /**
     * 检查数据是否有变化.
     *
     * @param array<mixed>|null $oldData
     * @param array<mixed>      $newData
     */
    public function hasChanges(?array $oldData, array $newData): bool
    {
        if (null === $oldData) {
            return false;
        }

        foreach (self::KEY_FIELDS as $field) {
            if ($this->fieldHasChanged($oldData, $newData, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取变更摘要.
     *
     * @param array<mixed>|null $oldData
     * @param array<mixed>      $newData
     *
     * @return array{
     *     has_changes: bool,
     *     changed_fields: string[],
     *     critical_changes: string[]
     * }
     */
    public function getChangeSummary(?array $oldData, array $newData): array
    {
        if (null === $oldData) {
            return [
                'has_changes' => false,
                'changed_fields' => [],
                'critical_changes' => [],
            ];
        }

        $changedFields = [];
        $criticalChanges = [];

        foreach (self::KEY_FIELDS as $field) {
            if ($this->fieldHasChanged($oldData, $newData, $field)) {
                $changedFields[] = $field;
                if ($this->isCriticalField($field)) {
                    $criticalChanges[] = $field;
                }
            }
        }

        return [
            'has_changes' => [] !== $changedFields,
            'changed_fields' => $changedFields,
            'critical_changes' => $criticalChanges,
        ];
    }

    /**
     * 检查值是否有变化.
     */
    private function valueHasChanged(mixed $oldValue, mixed $newValue): bool
    {
        // 特殊处理数组字段
        if (\is_array($oldValue) && \is_array($newValue)) {
            return json_encode($oldValue) !== json_encode($newValue);
        }

        return $oldValue !== $newValue;
    }

    /**
     * 检查字段是否有变化.
     *
     * @param array<mixed> $oldData
     * @param array<mixed> $newData
     */
    private function fieldHasChanged(array $oldData, array $newData, string $field): bool
    {
        $oldValue = $oldData[$field] ?? null;
        $newValue = $newData[$field] ?? null;

        return $this->valueHasChanged($oldValue, $newValue);
    }

    /**
     * 判断是否为关键字段.
     */
    private function isCriticalField(string $field): bool
    {
        $criticalFields = [
            'status',
            'department_ids',
            'is_tenant_manager',
        ];

        return \in_array($field, $criticalFields, true);
    }
}
