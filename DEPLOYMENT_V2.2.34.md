# V2.2.34 — Role Limits & Sales Expense Access

- Corrects tenant role usage counting so limits mean exactly 0=disabled, 1=one account, 2=two accounts.
- Removes duplicate top-level Sales navigation; Sales Report remains under Management.
- Allows Sales Representative to be delegated Layer, Broiler and Ruminant expense-entry permissions without granting production daily/feed access.
- Adds expense-entry links under Management when authorized.

Run migrations and `php scripts/verify_v2234_role_limits_sales_expenses.php`.
