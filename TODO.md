# TODO: Add Logging for Payment Gateway Functional Testing

## Objective
Add comprehensive logging to payment gateway integration components to generate evidence logs when the system is run.

## Steps
- [x] Add logging to MidtransService.php methods (createTransaction, handleNotification, createPayout, approvePayout)
- [x] Add logging to TransactionController.php methods (store, handlePaymentNotification, completeTransaction)
- [ ] Test the logging by running transactions in the system
- [ ] Collect logs from storage/logs/laravel.log for the testing report

## Files Modified
- app/Services/MidtransService.php - Added [PAYMENT GATEWAY] prefixed logs for all key methods
- app/Http/Controllers/TransactionController.php - Added [PAYMENT GATEWAY] prefixed logs for transaction creation, notification handling, and payout creation

## Expected Log Output
- Transaction creation logs with order ID, amount, customer details
- Payment notification processing logs with request details and status updates
- Payout creation logs with payout ID, amount, and seller details
- Error logs for failed operations
- Success confirmation logs for completed transactions

## How to Test
1. Run the Laravel application
2. Create a transaction (purchase carbon credits)
3. Use the send_midtrans_notification.php tool to simulate payment notifications
4. Check storage/logs/laravel.log for [PAYMENT GATEWAY] prefixed entries
