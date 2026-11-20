{{-- Sidebar akordeon: hanya satu grup terbuka. Saat halaman dimuat, yang terbuka grup halaman ini. --}}
<script>
    (() => {
        const groups = [...document.currentScript.closest('nav').querySelectorAll('.fi-sidebar-group:not([data-group-label=""])')];
        const labels = groups.map((group) => group.dataset.groupLabel);
        const collapsed = groups.filter((group) => ! group.classList.contains('fi-active')).map((group) => group.dataset.groupLabel);

        // Dibaca store sidebar Filament saat Alpine mulai.
        localStorage.setItem('filament:sidebar:collapsed_groups', JSON.stringify(collapsed));

        groups.forEach((group) => {
            const isCollapsed = collapsed.includes(group.dataset.groupLabel);

            group.classList.toggle('fi-collapsed', isCollapsed);
            group.querySelector('.fi-sidebar-group-items').style.display = isCollapsed ? 'none' : '';

            // Jumlah badge menu di dalamnya, tampil di judul grup selama grup tertutup.
            const badgeTotal = [...group.querySelectorAll('.fi-sidebar-item-badge-ctn .fi-badge')]
                .reduce((total, badge) => total + (parseInt(badge.textContent, 10) || 0), 0);

            if (badgeTotal > 0) {
                group.querySelector('.fi-sidebar-group-label').dataset.badge = badgeTotal;
            }
        });

        document.addEventListener('alpine:initialized', () => {
            const sidebar = window.Alpine.store('sidebar');

            sidebar.toggleCollapsedGroup = (label) => {
                sidebar.collapsedGroups = sidebar.groupIsCollapsed(label)
                    ? labels.filter((other) => other !== label)
                    : sidebar.collapsedGroups.concat(label);
            };
        });
    })();
</script>
