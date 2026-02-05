<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

use Inertia\Middleware;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'initials' => $this->getUserInitials($request->user()->name),
                    ]
                    : null,
            ],
            'flash' => [
                'success' => fn () => session('success'),
                'info'    => fn () => session('info'),
                'warning' => fn () => session('warning'),
                'error'   => fn () => session('error'),
            ],
            'app' => [
                'name' => config('app.name'),
            ],
            'route' => function () {
                $current = Route::current();
                return [
                    'name' => optional($current)->getName(),
                    'uri' => optional($current)->uri(),
                ];
            },
        ]);
    }


    /**
     * Gera até 3 iniciais do nome do usuário.
     * Ignora preposições e normaliza maiúsculas.
     */
    protected function getUserInitials(?string $name): string
    {
        if (!$name) {
            return '';
        }

        // Remove espaços extras e separa o nome
        $parts = preg_split('/\s+/', trim($name));

        // Palavras a serem ignoradas
        $ignore = ['de', 'da', 'do', 'das', 'dos', 'e', 'del', 'la', 'el'];

        $filtered = collect($parts)
            ->reject(fn($part) => in_array(Str::lower($part), $ignore))
            ->take(3) // até 3 iniciais
            ->map(fn($part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        return $filtered ?: '';
    }

}
