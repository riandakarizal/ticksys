<?php

use App\Models\User;

// Tests run without DB — instantiate User directly
function userWithRole(string $role): User
{
    $u = new User();
    $u->user_role = $role;
    return $u;
}

test('isSuperAdmin() hanya true untuk role superadmin', function () {
    expect(userWithRole('superadmin')->isSuperAdmin())->toBeTrue();
    expect(userWithRole('admin')->isSuperAdmin())->toBeFalse();
    expect(userWithRole('siteadmin')->isSuperAdmin())->toBeFalse();
    expect(userWithRole('user')->isSuperAdmin())->toBeFalse();
});

test('isAdmin() hanya true untuk role admin', function () {
    expect(userWithRole('admin')->isAdmin())->toBeTrue();
    expect(userWithRole('superadmin')->isAdmin())->toBeFalse();
    expect(userWithRole('siteadmin')->isAdmin())->toBeFalse();
    expect(userWithRole('user')->isAdmin())->toBeFalse();
});

test('isSiteAdmin() hanya true untuk role siteadmin', function () {
    expect(userWithRole('siteadmin')->isSiteAdmin())->toBeTrue();
    expect(userWithRole('admin')->isSiteAdmin())->toBeFalse();
});

test('isUser() hanya true untuk role user', function () {
    expect(userWithRole('user')->isUser())->toBeTrue();
    expect(userWithRole('admin')->isUser())->toBeFalse();
});

test('canViewReports() true untuk superadmin dan admin', function () {
    expect(userWithRole('superadmin')->canViewReports())->toBeTrue();
    expect(userWithRole('admin')->canViewReports())->toBeTrue();
    expect(userWithRole('siteadmin')->canViewReports())->toBeFalse();
    expect(userWithRole('user')->canViewReports())->toBeFalse();
});

test('canManageAllTickets() hanya true untuk superadmin', function () {
    expect(userWithRole('superadmin')->canManageAllTickets())->toBeTrue();
    expect(userWithRole('admin')->canManageAllTickets())->toBeFalse();
    expect(userWithRole('siteadmin')->canManageAllTickets())->toBeFalse();
    expect(userWithRole('user')->canManageAllTickets())->toBeFalse();
});
