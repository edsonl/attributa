<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\AuthPasswordController;
use App\Http\Controllers\Panel\AccountController;
use App\Http\Controllers\Panel\ClientController;
use App\Http\Controllers\Panel\CompanyController;
use App\Http\Controllers\Panel\TaskController;
use App\Http\Controllers\Panel\TaskNoteController;
use App\Http\Controllers\Panel\UserController;
use App\Http\Controllers\Panel\CampaignController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


use App\Http\Controllers\TrackingController;

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

Route::get('/sobre', function () {
    return Inertia::render('About', []);
})->name('sobre');

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


        });




// Trakink
Route::view('/produto-teste', 'tracking.produto-teste')->name('teste');

Route::post('/tracking/collect', [TrackingController::class, 'collect'])
    ->middleware('throttle:tracking')
    ->name('tracking.collect');

Route::get('/tracking/script.js', function () {
    return response()
        ->view('tracking.script')
        ->header('Content-Type', 'application/javascript')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
});
