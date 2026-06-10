<?php

use App\Models\User;

// Tests run without DB — instantiate User directly
function userWithRole(string $role): User
{
    $u = new User();
    $u->role = $role;
    return $u;
}

test('isAdmin() hanya true untuk role admin', function () {
    expect(userWithRole('admin')->isAdmin())->toBeTrue();
    expect(userWithRole('supervisor')->isAdmin())->toBeFalse();
    expect(userWithRole('agent')->isAdmin())->toBeFalse();
    expect(userWithRole('client')->isAdmin())->toBeFalse();
});

test('isSupervisor() hanya true untuk role supervisor', function () {
    expect(userWithRole('supervisor')->isSupervisor())->toBeTrue();
    expect(userWithRole('admin')->isSupervisor())->toBeFalse();
    expect(userWithRole('agent')->isSupervisor())->toBeFalse();
    expect(userWithRole('client')->isSupervisor())->toBeFalse();
});

test('isAgent() hanya true untuk role agent', function () {
    expect(userWithRole('agent')->isAgent())->toBeTrue();
    expect(userWithRole('admin')->isAgent())->toBeFalse();
});

test('isClient() hanya true untuk role client', function () {
    expect(userWithRole('client')->isClient())->toBeTrue();
    expect(userWithRole('admin')->isClient())->toBeFalse();
});

test('canViewReports() true untuk admin dan supervisor', function () {
    expect(userWithRole('admin')->canViewReports())->toBeTrue();
    expect(userWithRole('supervisor')->canViewReports())->toBeTrue();
    expect(userWithRole('agent')->canViewReports())->toBeFalse();
    expect(userWithRole('client')->canViewReports())->toBeFalse();
});

test('canManageAllTickets() hanya true untuk admin', function () {
    expect(userWithRole('admin')->canManageAllTickets())->toBeTrue();
    expect(userWithRole('supervisor')->canManageAllTickets())->toBeFalse();
    expect(userWithRole('agent')->canManageAllTickets())->toBeFalse();
    expect(userWithRole('client')->canManageAllTickets())->toBeFalse();
});
