# V2.2.35 — Exact Role Quotas & Child-Aware Navigation

- Farm Admin is explicitly excluded from specialist-role quota counting, even if legacy user_roles rows exist.
- Role limits are exact: 0 blocks, 1 permits one specialist account, 2 permits two, etc.
- Poultry and Ruminant parent menus appear when any permitted child page is available.
- Expense-only users can reach Layer/Broiler/Ruminant expense pages without receiving overview permission.
- Duplicate expense-entry links were removed from Management.
- Sales Report remains under Management.

Run `php scripts/verify_v2235_role_quota_child_navigation.php` after deployment.
