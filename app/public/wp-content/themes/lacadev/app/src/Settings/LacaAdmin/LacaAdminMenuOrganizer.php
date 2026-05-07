<?php

namespace App\Settings\LacaAdmin;

/**
 * Keeps the Laca Admin submenu grouped in one predictable order.
 *
 * Individual modules still own their page callbacks and capabilities. This
 * class only reorganizes the final WordPress submenu array after all pages are
 * registered, so existing URLs and callbacks remain unchanged.
 */
class LacaAdminMenuOrganizer
{
    private const PARENT_SLUG = 'laca-admin';

    /**
     * @var array<int,array{label:string,items:string[]}>
     */
    private array $decoratorGroups = [];

    /**
     * @var array<string,array{label:string,items:string[]}>
     */
    private const GROUPS = [
        'general' => [
            'label' => 'Tổng quan & cấu hình',
            'items' => [
                'laca-admin',
                'laca-management-settings',
            ],
        ],
        'maintenance' => [
            'label' => 'Hiệu năng & bảo trì',
            'items' => [
                'laca-tools',
                'laca-db-cleaner',
                'laca-email-log',
            ],
        ],
        'security' => [
            'label' => 'Bảo mật & đăng nhập',
            'items' => [
                'laca-security',
                'laca-recaptcha',
                'laca-login-socials',
            ],
        ],
        'content' => [
            'label' => 'Nội dung & cấu trúc',
            'items' => [
                'laca-dynamic-cpt',
                'laca-contact-forms',
            ],
        ],
        'projects' => [
            'label' => 'Dự án & thông báo',
            'items' => [
                'laca-project-notifications',
            ],
        ],
        'marketing' => [
            'label' => 'Marketing & AI',
            'items' => [
                'laca-exit-popup',
                'laca-chatbot',
            ],
        ],
    ];

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'organize'], PHP_INT_MAX);
        add_action('admin_head', [$this, 'printStyles']);
        add_action('admin_footer', [$this, 'printScript']);
    }

    public function organize(): void
    {
        global $submenu;

        if (empty($submenu[self::PARENT_SLUG]) || !is_array($submenu[self::PARENT_SLUG])) {
            return;
        }

        $itemsBySlug = [];
        $unassigned = [];

        foreach ($submenu[self::PARENT_SLUG] as $item) {
            $slug = (string) ($item[2] ?? '');

            if ($slug === '') {
                continue;
            }

            $itemsBySlug[$slug] = $item;
            $unassigned[$slug] = $item;
        }

        $organized = [];
        $decoratorGroups = [];

        foreach (self::GROUPS as $group) {
            $groupSlugs = [];

            foreach ($group['items'] as $slug) {
                if (!isset($itemsBySlug[$slug])) {
                    continue;
                }

                $organized[] = $itemsBySlug[$slug];
                $groupSlugs[] = $slug;
                unset($unassigned[$slug]);
            }

            if ($groupSlugs !== []) {
                $decoratorGroups[] = [
                    'label' => $group['label'],
                    'items' => $groupSlugs,
                ];
            }
        }

        if ($unassigned !== []) {
            array_push($organized, ...array_values($unassigned));
            $decoratorGroups[] = [
                'label' => 'Khác',
                'items' => array_keys($unassigned),
            ];
        }

        $submenu[self::PARENT_SLUG] = $organized;
        $this->decoratorGroups = $decoratorGroups;
    }

    /**
     * @return array<int,array{label:string,items:string[]}>
     */
    private function getGroupsForScript(): array
    {
        if ($this->decoratorGroups !== []) {
            return $this->decoratorGroups;
        }

        return array_values(self::GROUPS);
    }

    public function printStyles(): void
    {
        ?>
        <style>
            #toplevel_page_laca-admin .wp-submenu li.laca-admin-menu-group-start::before {
                border-top: 1px solid rgba(240, 246, 252, .12);
                color: #a7aaad;
                content: attr(data-laca-group-label);
                display: block;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0;
                line-height: 1.25;
                margin: 10px 12px 4px;
                padding-top: 8px;
                white-space: normal;
            }

            #toplevel_page_laca-admin .wp-submenu li.laca-admin-menu-group-start:first-child::before {
                border-top: 0;
                margin-top: 4px;
                padding-top: 2px;
            }
        </style>
        <?php
    }

    public function printScript(): void
    {
        ?>
        <script>
            (() => {
                const groups = <?php echo wp_json_encode($this->getGroupsForScript()); ?>;
                const submenu = document.querySelector('#toplevel_page_laca-admin .wp-submenu');

                if (!submenu || !Array.isArray(groups)) {
                    return;
                }

                const findMenuItem = (slug) => {
                    return Array.from(submenu.querySelectorAll('a[href]')).find((item) => {
                        try {
                            return new URL(item.href, window.location.href).searchParams.get('page') === slug;
                        } catch (error) {
                            return false;
                        }
                    });
                };

                groups.forEach((group) => {
                    const firstItem = group.items
                        .map(findMenuItem)
                        .find(Boolean);

                    if (!firstItem || !firstItem.parentElement) {
                        return;
                    }

                    firstItem.parentElement.classList.add('laca-admin-menu-group-start');
                    firstItem.parentElement.dataset.lacaGroupLabel = group.label;
                });
            });
        </script>
        <?php
    }
}
