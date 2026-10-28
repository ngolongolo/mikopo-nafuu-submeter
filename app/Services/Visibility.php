<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\SupplierProfile;
use App\Models\User;

class Visibility
{
    public static function supplierProfile(User $user): ?SupplierProfile
    {
        return $user->supplierProfiles()->first() ?? $user->supplierProfile;
    }

    public static function supplierUserIds(User $user): array
    {
        $profile = self::supplierProfile($user);
        if (!$profile) {
            return [$user->id];
        }

        return $profile->users()->pluck('users.id')->push($profile->user_id)->unique()->values()->all();
    }

    public static function scope($query, User $user)
    {
        return match ($user->role) {
            'admin', 'officer' => $query,
            'financier' => $query->where('financier_id', $user->financier_id ?? 0),
            'supplier' => self::scopeSupplier($query, $user),
            default => $query->where('user_id', $user->id),
        };
    }

    public static function check($record, User $user): void
    {
        $allowed = in_array($user->role, ['admin', 'officer'], true)
            || ($user->role === 'financier' && $user->financier_id && $record->financier_id === $user->financier_id)
            || ($user->role === 'supplier' && self::supplierCanAccess($record, $user))
            || ($user->role === 'customer' && $record->user_id === $user->id);

        abort_unless($allowed, 403);
    }

    private static function scopeSupplier($query, User $user)
    {
        $profile = self::supplierProfile($user);
        $userIds = self::supplierUserIds($user);
        $model = $query->getModel();

        if ($model instanceof Loan) {
            return $query->where(fn ($loan) => self::applyLoanSupplierCondition($loan, $profile?->id, $userIds));
        }

        if ($model instanceof LoanApplication) {
            return $query->where(function ($application) use ($profile, $userIds) {
                $application
                    ->whereHas('loan', fn ($loan) => self::applyLoanSupplierCondition($loan, $profile?->id, $userIds))
                    ->orWhere(function ($unapproved) use ($profile, $userIds) {
                        $unapproved->whereDoesntHave('loan');
                        self::applyApplicationSupplierCondition($unapproved, $profile?->id, $userIds);
                    });
            });
        }

        return $query->whereHas('customer', fn ($customer) => $customer->whereIn('onboarded_by', $userIds));
    }

    private static function applyLoanSupplierCondition($query, ?int $profileId, array $userIds): void
    {
        $query->where(function ($assignment) use ($profileId, $userIds) {
            if ($profileId) {
                $assignment->where('supplier_profile_id', $profileId)
                    ->orWhere(function ($legacy) use ($userIds) {
                        $legacy->whereNull('supplier_profile_id')
                            ->whereHas('customer', fn ($customer) => $customer->whereIn('onboarded_by', $userIds));
                    });

                return;
            }

            $assignment->whereNull('supplier_profile_id')
                ->whereHas('customer', fn ($customer) => $customer->whereIn('onboarded_by', $userIds));
        });
    }

    private static function applyApplicationSupplierCondition($query, ?int $profileId, array $userIds): void
    {
        $query->where(function ($assignment) use ($profileId, $userIds) {
            if ($profileId) {
                $assignment->where('supplier_profile_id', $profileId)
                    ->orWhere(function ($legacy) use ($userIds) {
                        $legacy->whereNull('supplier_profile_id')
                            ->whereHas('customer', fn ($customer) => $customer->whereIn('onboarded_by', $userIds));
                    });

                return;
            }

            $assignment->whereNull('supplier_profile_id')
                ->whereHas('customer', fn ($customer) => $customer->whereIn('onboarded_by', $userIds));
        });
    }

    private static function supplierCanAccess($record, User $user): bool
    {
        if ($record instanceof LoanApplication) {
            if ($record->loan) {
                return self::supplierCanAccess($record->loan, $user);
            }

            $profileId = self::supplierProfile($user)?->id;
            if ($record->supplier_profile_id !== null) {
                return $profileId !== null && (int) $record->supplier_profile_id === (int) $profileId;
            }
        }

        if ($record instanceof Loan) {
            $profileId = self::supplierProfile($user)?->id;
            if ($record->supplier_profile_id !== null) {
                return $profileId !== null && (int) $record->supplier_profile_id === (int) $profileId;
            }
        }

        return $record->customer()->whereIn('onboarded_by', self::supplierUserIds($user))->exists();
    }
}
