<?php

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

/**
 * Sidebar admin: label grup => label menu, sesuai urutan tampil.
 *
 * @return array<string, list<string>>
 */
function adminSidebar(): array
{
    Filament::setCurrentPanel('admin');

    return collect(Filament::getNavigation())
        ->mapWithKeys(fn (NavigationGroup $group): array => [
            (string) $group->getLabel() => collect($group->getItems())->map(fn (NavigationItem $item): string => $item->getLabel())->values()->all(),
        ])
        ->all();
}

test('the admin sidebar groups every menu by its job, in a fixed order', function () {
    $this->actingAs(userWithRole('super_admin'));

    expect(adminSidebar())->toBe([
        '' => ['Dashboard', 'Kalender', 'Laporan'],
        'Inventaris' => ['Aset', 'Lisensi', 'Barang Habis Pakai', 'Aksesori', 'Komponen', 'Paket'],
        'Transaksi' => ['Permintaan Aset', 'Serah-terima', 'Peminjaman', 'Perbaikan', 'Stock Opname', 'Penghapusan'],
        'Master Data' => ['Model Aset', 'Kategori', 'Kelas Penyusutan', 'Produsen', 'Status', 'Supplier'],
        'Organisasi' => ['Gedung', 'Departemen', 'Karyawan', 'Ruang'],
        'Administrasi' => ['Perusahaan', 'Peran', 'User', 'Log masuk', 'Backup', 'Log aktivitas', 'Pengaturan'],
    ]);
});

test('the sidebar groups keep their order in English', function () {
    $this->actingAs(userWithRole('super_admin'));
    app()->setLocale('en');

    expect(array_keys(adminSidebar()))->toBe(['', 'Inventory', 'Transactions', 'Master Data', 'Organization', 'Administration']);
});

test('the admin sidebar keeps only one group open', function () {
    $this->actingAs(userWithRole('super_admin'));

    $this->get('/admin')
        ->assertOk()
        ->assertSee('sidebar.toggleCollapsedGroup = (label)', false);
});
