<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\AuthPasswordController;
use App\Http\Controllers\Panel\AccountController;
use App\Http\Controllers\Panel\ActivityController;
use App\Http\Controllers\Panel\CampaignController;
use App\Http\Controllers\Panel\ClientController;
use App\Http\Controllers\Panel\CompanyController;
use App\Http\Controllers\Panel\ConversionsController;
use App\Http\Controllers\Panel\ConversionGoalController;
use App\Http\Controllers\Panel\CountryController;
use App\Http\Controllers\Panel\CampaignStatusController;
use App\Http\Controllers\Panel\AffiliatePlatformController;
use App\Http\Controllers\Panel\BrowserController;
use App\Http\Controllers\Panel\DeviceCategoryController;
use App\Http\Controllers\Panel\TrafficSourceCategoryController;
use App\Http\Controllers\Panel\GoogleAdsAccountController;
use App\Http\Controllers\Panel\GoogleAuthController;
use App\Http\Controllers\Panel\TaskController;
use App\Http\Controllers\Panel\TaskNoteController;
use App\Http\Controllers\Panel\UserController;
use App\Jobs\ProcessIpClassificationJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::view('/', 'site.home')->name('home');


// redireciona /home para a home canônica
Route::redirect('/home', '/');

Route::redirect('/campanhas', '/painel/campaigns');
Route::redirect('/painel', '/painel/atividade/pageviews')->name('panel.index');

Route::get('/teste', function () {


    $response = Http::timeout(10)
        ->withoutVerifying() // 👈 desativa verificação SSL
        ->get('https://api.ipgeolocation.io/v3/ipgeo?apiKey=' . config('services.ipgeolocation.key') . '&ip=8.8.8.8')
        ->json();

    return $response;

    //ProcessIpClassificationJob::dispatch();
   // return response()->json([
   //     'message' => 'Processamento de IPs iniciado com sucesso.'
   // ]);
})->name('teste');

//Route::get('/dashboard', function () {
//    return Inertia::render('Dashboard', ['title' => 'Dashboard']);
///})->name('dashboard');

// públicas (apenas visitantes)
Route::middleware('guest')->group(function () {


    Route::get('/login',    [AuthController::class, 'showLogin'])->name('auth.login.show');
    Route::post('/login',   [AuthController::class, 'login'])->name('auth.login');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('auth.register.show');
    Route::post('/register',[AuthController::class, 'register'])->name('auth.register');

    // Solicitar link por e-mail
    Route::get('/auth/forgot-password',[AuthPasswordController::class, 'showLinkRequestForm'])
        ->name('password.request');
    Route::post('/auth/forgot-password',[AuthPasswordController::class, 'sendResetLinkEmail'])
        ->name('password.email');

    // Form de redefinição
    Route::get('/auth/reset-password/{token}',[AuthPasswordController::class, 'showResetForm'])
        ->name('password.reset');

    // Submeter nova senha
    Route::post('/auth/reset-password',[AuthPasswordController::class, 'reset'])
        ->name('password.update');

});

// Logout (auth)
Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('auth.logout');

// protegidas (usuário autenticado)
Route::middleware('auth')->group(function () {
    // exemplo: página que usa AppLayout
    Route::get('/dashboard', fn () => Inertia::render('Dashboard',['title'=>'Dashboard']))->name('dashboard');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
});

// protegidas (usuário autenticado)
Route::middleware(['auth', 'verified'])
        ->prefix('painel')
        ->name('panel.')
        ->group(function () {


        // =========================
        // Atividade
        // =========================
            Route::prefix('atividade')
                ->name('atividade.')
                ->group(function () {

                // Tela (Inertia)
                Route::get('/pageviews', [ActivityController::class, 'pageviews'])
                    ->name('pageviews');

                // API
                Route::get('/pageviews/data', [ActivityController::class, 'data'])
                    ->name('pageviews.data');

                Route::get('/pageviews/{pageview}', [ActivityController::class, 'show'])
                    ->name('pageviews.show');

                Route::delete('/pageviews', [ActivityController::class, 'bulkDestroy'])
                    ->name('pageviews.bulk-destroy');

                Route::delete('/pageviews/{pageview}', [ActivityController::class, 'destroy'])
                    ->name('pageviews.destroy');

                Route::get('/campaigns', [ActivityController::class, 'campaigns'])
                    ->name('campaigns');
            });

            Route::prefix('conversoes')
                ->name('conversoes.')
                ->group(function () {
                    // Tela (Inertia)
                    Route::get('/', [ConversionsController::class, 'index'])->name('index');
                    // API (dados)
                    Route::get('/data', [ConversionsController::class, 'data'])->name('data');
                    // Campanhas (filtro)
                    Route::get('/campaigns', [ConversionsController::class, 'campaigns'])->name('campaigns');
                    // Timezones para cadastro manual
                    Route::get('/timezones', [ConversionsController::class, 'timezones'])->name('timezones');
                    // Metadados de exportação (range)
                    Route::get('/export/range', [ConversionsController::class, 'exportRange'])->name('export-range');
                    // Exportação CSV (Google import)
                    Route::get('/export/csv', [ConversionsController::class, 'exportCsv'])->name('export-csv');
                    // Cadastro manual
                    Route::post('/manual', [ConversionsController::class, 'storeManual'])->name('store-manual');
                    // Exclusão de conversão
                    Route::delete('/{conversion}', [ConversionsController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('configuracoes')->middleware(['auth'])->group(function () {
                Route::get('contas-anuncios', [GoogleAdsAccountController::class, 'index'])
                    ->name('ads-accounts.index');
                Route::patch(
                    'configuracoes/contas-anuncios/{account}/toggle',
                    [GoogleAdsAccountController::class, 'toggle']
                )->name('ads-accounts.toggle');

            });


            //Crud Campanhas :
            Route::get('campaigns/{campaign}/tracking-code', [CampaignController::class, 'tracking_code'])
                ->name('campaigns.tracking_code');
            Route::get('campaigns/{campaign}/countries', [CampaignController::class, 'countries'])
                ->name('campaigns.countries');
            Route::patch('campaigns/{campaign}/toggle-status', [CampaignController::class, 'toggleStatus'])
                ->name('campaigns.toggle-status');
            Route::patch('campaigns/{campaign}/status', [CampaignController::class, 'updateStatus'])
                ->name('campaigns.update-status');
            Route::resource('campaigns', CampaignController::class);
            Route::get('conversion-goals/{conversion_goal}/logs', [ConversionGoalController::class, 'logs'])
                ->name('conversion-goals.logs');
            Route::get('conversion-goals/{conversion_goal}/snapshot', [ConversionGoalController::class, 'snapshot'])
                ->name('conversion-goals.snapshot');
            Route::get('conversion-goals/{conversion_goal}/snapshot/csv', [ConversionGoalController::class, 'snapshotCsv'])
                ->name('conversion-goals.snapshot-csv');
            Route::delete('conversion-goals/{conversion_goal}/logs', [ConversionGoalController::class, 'destroyLogs'])
                ->name('conversion-goals.logs.destroy');
            Route::patch('conversion-goals/{conversion_goal}/regenerate-password', [ConversionGoalController::class, 'regeneratePassword'])
                ->name('conversion-goals.regenerate-password');
            Route::resource('conversion-goals', ConversionGoalController::class)->except(['show']);

            //CRUD Usuários
            // bulk delete
            Route::delete('users/bulk', [UserController::class, 'bulkDestroy'])
                ->name('users.bulk-destroy');
            Route::resource('users', UserController::class)->except(['show']);

            // Countries (AJAX-first CRUD)
            Route::prefix('countries')
                ->name('countries.')
                ->group(function () {
                    Route::get('/', [CountryController::class, 'index'])->name('index');
                    Route::get('/data', [CountryController::class, 'data'])->name('data');
                    Route::post('/', [CountryController::class, 'store'])->name('store');
                    Route::put('/{country}', [CountryController::class, 'update'])->name('update');
                    Route::delete('/{country}', [CountryController::class, 'destroy'])->name('destroy');
                });

            Route::prefix('campaign-statuses')
                ->name('campaign-statuses.')
                ->group(function () {
                    Route::get('/', [CampaignStatusController::class, 'index'])->name('index');
                    Route::get('/data', [CampaignStatusController::class, 'data'])->name('data');
                    Route::post('/', [CampaignStatusController::class, 'store'])->name('store');
                    Route::put('/{campaign_status}', [CampaignStatusController::class, 'update'])->name('update');
                    Route::delete('/{campaign_status}', [CampaignStatusController::class, 'destroy'])->name('destroy');
                });

            Route::prefix('browsers')
                ->name('browsers.')
                ->group(function () {
                    Route::get('/', [BrowserController::class, 'index'])->name('index');
                    Route::get('/data', [BrowserController::class, 'data'])->name('data');
                    Route::post('/', [BrowserController::class, 'store'])->name('store');
                    Route::put('/{browser}', [BrowserController::class, 'update'])->name('update');
                    Route::delete('/{browser}', [BrowserController::class, 'destroy'])->name('destroy');
                });

            Route::prefix('device-categories')
                ->name('device-categories.')
                ->group(function () {
                    Route::get('/', [DeviceCategoryController::class, 'index'])->name('index');
                    Route::get('/data', [DeviceCategoryController::class, 'data'])->name('data');
                    Route::post('/', [DeviceCategoryController::class, 'store'])->name('store');
                    Route::put('/{device_category}', [DeviceCategoryController::class, 'update'])->name('update');
                    Route::delete('/{device_category}', [DeviceCategoryController::class, 'destroy'])->name('destroy');
                });

            Route::prefix('traffic-source-categories')
                ->name('traffic-source-categories.')
                ->group(function () {
                    Route::get('/', [TrafficSourceCategoryController::class, 'index'])->name('index');
                    Route::get('/data', [TrafficSourceCategoryController::class, 'data'])->name('data');
                    Route::post('/', [TrafficSourceCategoryController::class, 'store'])->name('store');
                    Route::put('/{traffic_source_category}', [TrafficSourceCategoryController::class, 'update'])->name('update');
                    Route::delete('/{traffic_source_category}', [TrafficSourceCategoryController::class, 'destroy'])->name('destroy');
                });

            Route::prefix('affiliate-platforms')
                ->name('affiliate-platforms.')
                ->group(function () {
                    Route::get('/', [AffiliatePlatformController::class, 'index'])->name('index');
                    Route::get('/data', [AffiliatePlatformController::class, 'data'])->name('data');
                    Route::post('/', [AffiliatePlatformController::class, 'store'])->name('store');
                    Route::put('/{affiliate_platform}', [AffiliatePlatformController::class, 'update'])->name('update');
                    Route::delete('/{affiliate_platform}', [AffiliatePlatformController::class, 'destroy'])->name('destroy');
                });


        });


        Route::prefix('painel')->group(function () {

            Route::get('/autenticacao/google',
                [GoogleAuthController::class, 'redirect']
            )->name('panel.google.auth');

            Route::get('/autenticacao/google/callback',
                [GoogleAuthController::class, 'callback']
            )->name('panel.google.callback');

        });




// Trakink
Route::view('/produto-teste', 'tracking.produto-teste')->name('teste');
