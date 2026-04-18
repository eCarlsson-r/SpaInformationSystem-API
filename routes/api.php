<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompensationController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseItemController;
use App\Http\Controllers\ExpensePaymentController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\IncomeItemController;
use App\Http\Controllers\IncomePaymentController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WalkinController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\SentimentController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ConflictController;
use App\Http\Controllers\ContentGenerationController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/subscribe', [AuthController::class, 'subscribe']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/dashboard', [DashboardController::class, 'dashboard']);
    Route::get('/daily', [DashboardController::class, 'daily']);
    Route::get('account/lookup', [AccountController::class, 'lookup']);
    Route::post('/attendance/sync', [AttendanceController::class, 'sync']);
    Route::get('/attendance/sync-status', [AttendanceController::class, 'getSyncStatus']);

    Route::apiResource('session', SessionController::class);
    Route::post('/session/{session}/start', [SessionController::class, 'start']);
    Route::post('/session/{session}/finish', [SessionController::class, 'finish']);
    // Alias for integration tests that use plural form
    Route::post('/sessions/{session}/finish', [SessionController::class, 'finish']);

    Route::apiResource('journal', JournalController::class);
    Route::apiResource('income', IncomeController::class);
    Route::apiResource('incomeitem', IncomeItemController::class);
    Route::apiResource('incomepayment', IncomePaymentController::class);
    Route::apiResource('expense', ExpenseController::class);
    Route::apiResource('expenseitem', ExpenseItemController::class);
    Route::apiResource('expensepayment', ExpensePaymentController::class);
    Route::apiResource('transfer', TransferController::class); 
    Route::apiResource('attendance', AttendanceController::class); 
    Route::apiResource('period', PeriodController::class);
    Route::apiResource('shift', ShiftController::class);
    Route::apiResource('compensation', CompensationController::class);
    Route::apiResource('account', AccountController::class);
    Route::apiResource('wallet', WalletController::class);
    Route::apiResource('discount', DiscountController::class);
    Route::apiResource('agent', AgentController::class);
    Route::apiResource('bank', BankController::class);
    Route::apiResource('room', RoomController::class);
    Route::apiResource('branch', BranchController::class);
    Route::apiResource('customer', CustomerController::class);
    Route::apiResource('employee', EmployeeController::class);
    Route::apiResource('bonus', BonusController::class); 
    Route::apiResource('category', CategoryController::class);
    Route::apiResource('treatment', TreatmentController::class); 
    Route::apiResource('supplier', SupplierController::class); 
    Route::apiResource('voucher', VoucherController::class); 
    Route::apiResource('walkin', WalkinController::class); 
    Route::apiResource('sales', SalesController::class);
    Route::apiResource('banner', BannerController::class);

    Route::apiResource('cart', CartController::class);
    Route::post('/cart/session', [CartController::class, 'bookSession']);
    Route::post('/cart/voucher', [CartController::class, 'buyVoucher']);

    // AI Features
    Route::post('/feedback', [FeedbackController::class, 'store']);
    Route::get('/ai/sentiment/dashboard', [SentimentController::class, 'dashboard']);
    Route::get('/ai/sentiment/summary', [SentimentController::class, 'summary']);

    // Recommendations (Requirements: 1.1, 1.4, 2.1, 2.2, 3.2)
    Route::get('/ai/recommendations', [RecommendationController::class, 'index']);
    Route::get('/ai/recommendations/pos', [RecommendationController::class, 'pos']);
    Route::post('/ai/recommendations/invalidate/{customerId}', [RecommendationController::class, 'invalidate']);

    // Chatbot (Requirements: 4.2, 4.5, 5.2, 5.4)
    Route::post('/ai/chat', [ChatbotController::class, 'customer']);
    Route::post('/ai/chat/staff', [ChatbotController::class, 'staff']);

    // Conflicts (Requirements: 7.3, 7.4, 7.6, 8.2, 8.3)
    Route::get('/conflicts', [ConflictController::class, 'index']);
    Route::get('/conflicts/pending', [ConflictController::class, 'pending']);
    Route::post('/conflicts/{id}/dismiss', [ConflictController::class, 'dismiss']);
    Route::post('/bookings/{id}/reschedule', [ConflictController::class, 'reschedule']);

    // Content Generation
    Route::post('/ai/generate-description', [ContentGenerationController::class, 'generateDescription']);
    Route::post('/ai/generate-image', [ContentGenerationController::class, 'generateImage']);
});

Route::get('/banner', [BannerController::class, 'index']);
Route::get('/branch', [BranchController::class, 'index']);
Route::get('/treatment', [TreatmentController::class, 'index']);
Route::apiResource('category', CategoryController::class);
Route::get('/room/available', [RoomController::class, 'available']);
Route::get('/room', [RoomController::class, 'index']);
Route::get('/employee', [EmployeeController::class, 'index']);