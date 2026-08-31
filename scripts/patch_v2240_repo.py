from pathlib import Path

p = Path('management/sales_records.php')
s = p.read_text()

replacements = [
    (
        """                $openSalesStmt->execute([$tenantFarmId, $tenantFarmId, $customerName]);
                $openSales = $openSalesStmt->fetchAll();

                foreach ($openSales as $openSale) {
""",
        """                $openSalesStmt->execute([$tenantFarmId, $tenantFarmId, $customerName]);
                $openSales = $openSalesStmt->fetchAll();

                $customerOutstanding = 0.0;
                foreach ($openSales as $openSale) {
                    $customerOutstanding += max(0.0, (float)$openSale['balance']);
                }

                if ($customerOutstanding <= 0.00001) {
                    throw new RuntimeException('This customer has no outstanding balance to settle.');
                }
                if ($paymentAmount > $customerOutstanding + 0.00001) {
                    throw new RuntimeException('Payment is greater than customer outstanding balance (₦' . number_format($customerOutstanding, 2) . ').');
                }

                foreach ($openSales as $openSale) {
""",
    ),
    (
        """                if ($remainingPayment > 0.00001) {
                    $insertPaymentEntry(
                        $remainingPayment,
                        null,
                        $defaultNote . ' | Advance payment (no open sale to allocate)'
                    );
                }
""",
        """                if ($remainingPayment > 0.00001) {
                    throw new RuntimeException('Payment could not be fully allocated to open receivables. No advance payment was recorded.');
                }
""",
    ),
    (
        """                                <label>Amount Paid (₦)</label>
                                <input type=\"number\" name=\"payment_amount\" class=\"form-control\" step=\"0.01\" min=\"0.01\" required>
""",
        """                                <label>Amount Paid (₦)</label>
                                <input type=\"number\" name=\"payment_amount\" class=\"form-control\" step=\"0.01\" min=\"0.01\"
                                       <?php if ($selectedCustomer !== '' && $selectedCustomerBalance > 0): ?>max=\"<?php echo htmlspecialchars(number_format($selectedCustomerBalance, 2, '.', '')); ?>\"<?php endif; ?>
                                       <?php echo ($selectedCustomer !== '' && $selectedCustomerBalance <= 0) ? 'disabled' : ''; ?> required>
                                <?php if ($selectedCustomer !== ''): ?>
                                    <small class=\"text-muted d-block mt-1\">Outstanding available to settle: ₦<?php echo number_format(max(0, $selectedCustomerBalance), 2); ?></small>
                                <?php endif; ?>
""",
    ),
    (
        """                        <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Cancel</button>
                        <button type=\"submit\" name=\"record_payment\" class=\"btn btn-success\">Save Payment</button>
""",
        """                        <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Cancel</button>
                        <button type=\"submit\" name=\"record_payment\" class=\"btn btn-success\" <?php echo ($selectedCustomer !== '' && $selectedCustomerBalance <= 0) ? 'disabled' : ''; ?>>Save Payment</button>
""",
    ),
]

for i, (old, new) in enumerate(replacements, 1):
    if old not in s:
        raise SystemExit(f'Expected V2.2.39 block {i} not found; refusing unsafe patch')
    s = s.replace(old, new, 1)

p.write_text(s)
print('V2.2.40 patch applied to management/sales_records.php')
