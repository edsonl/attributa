<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\AuthPasswordController;
use App\Http\Controllers\ConversionCallbackController;
use App\Http\Controllers\Panel\AccountController;
use App\Http\Controllers\Panel\ActivityController;
use App\Http\Controllers\Panel\CampaignController;
use App\Http\Controllers\Panel\ClientController;
use App\Http\Controllers\Panel\CompanyController;
use App\Http\Controllers\Panel\ConversionsController;
use App\Http\Controllers\Panel\CountryController;
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
            Route::resource('campaigns', CampaignController::class);

            //CRUD Usuários
            // bulk delete
            Route::delete('users/bulk', [UserController::class, 'bulkDestroy'])
                ->name('users.bulk-destroy');
            Route::resource('users', UserController::class)->except(['show']);

            //CRUD Tarefas
            Route::resource('tasks', TaskController::class);
            // GET painel/tasks/{task}/description
            Route::get('tasks/{task}/description', [TaskController::class, 'description'])->name('tasks.description');
            // Atualizar status da tarefa (AJAX)
            Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])
                ->name('tasks.updateStatus');
            // Atualizar Priority da tarefa (AJAX)
            Route::patch('tasks/{task}/priority', [TaskController::class, 'updatePriority'])
                ->name('tasks.updatePriority');

            Route::get('tasks/{task}/notes', [TaskNoteController::class, 'index'])->name('tasks.notes.index');
            Route::post('tasks/{task}/notes', [TaskNoteController::class, 'store'])->name('tasks.notes.store');
            Route::delete('tasks/{task}/notes/{note}', [TaskNoteController::class, 'destroy'])->name('tasks.notes.destroy');
            Route::put('tasks/{task}/notes/{note}', [TaskNoteController::class, 'update'])->name('tasks.notes.update');

            Route::resource('companies', CompanyController::class)->except(['show']);


            // CRUD Clients
            // bulk delete
            Route::delete('clients/bulk-delete', [ClientController::class, 'bulkDestroy'])
                ->name('clients.bulk-delete');
            Route::resource('clients', ClientController::class)->except(['show']);

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

//Resposta de conversão plataforma de afiliado
Route::get('/callback/conversion', [ConversionCallbackController::class, 'handle']);
