<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ClientStoreRequest;
use App\Http\Requests\Panel\ClientUpdateRequest;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image; // v3


class ClientController extends Controller
{
    public function index(Request $request)
    {
        $perPage   = (int) $request->input('per_page', 10);
        $search    = trim((string) $request->input('search', ''));
        $sortBy    = $request->input('sort', 'id');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Whitelist de colunas ordenáveis
        $sortable = ['id', 'name', 'website', 'order', 'visible', 'created_at'];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'id';
        }

        $query = Client::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('website', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortBy, $direction);

        $clients = $query->paginate($perPage)
            ->through(fn ($client) => [
                'id'            => $client->id,
                'name'          => $client->name,
                'website'       => $client->website,
                'order'         => $client->order,
                'visible'       => (bool) $client->visible,
                'visible_label' => $client->visible ? 'Sim' : 'Não',
                'image_url'     => $client->image_url,
                'created_at'    => $client->created_at?->format('d/m/Y H:i'),
            ])
            ->appends($request->query());

        return inertia('Panel/Clients/Index', [
            'items' => $clients,
            'filters' => [
                'search'    => $search,
                'per_page'  => $perPage,
                'sort'      => $sortBy,
                'direction' => $direction,
            ],
            'meta' => [
                'sortable' => $sortable,
            ],
        ])->with('title', 'Gerenciar clientes');

    }

    public function create()
    {
        return inertia('Panel/Clients/Create', [
            'title' => 'Adicionar cliente',
            'defaults' => [
                'visible' => true,
                'order' => 0,
                'image_max_width' => 300,
                'image_max_height' => 300,
            ],
        ]);
    }

    public function store(ClientStoreRequest $request)
    {
        $data = $request->validated();
        $meta = [
            'max_width' => (int)($data['image_max_width'] ?? 300),
            'max_height' => (int)($data['image_max_height'] ?? 300),
        ];

        $path = null;
        if ($request->hasFile('image')) {
            $path = $this->processAndStoreImage($request->file('image'), $data['name'], $meta);
        }

        $client = Client::create([
            'name' => $data['name'],
            'website' => $data['website'] ?? null,
            'image_path' => $path,
            'image_meta' => $meta,
            'order' => (int)$data['order'],
            'visible' => (bool)$data['visible'],
        ]);

        return redirect()->route('panel.clients.index')->with('success', 'Cliente criado.');
    }

    public function edit(Client $client)
    {
        return inertia('Panel/Clients/Edit', [
            'title' => 'Editar cliente',
            'client' => $client->only('id','name','website','image_path','order','visible') + [
                    'image_url' => $client->image_url,
                ],
            'image_meta' => $client->image_meta ?? ['max_width'=>300,'max_height'=>300],
        ]);
    }

    public function update(Client $client, ClientUpdateRequest $request)
    {

       // Log::info('UPDATE payload', $request->all());
        $data = $request->validated();
       // Log::info('UPDATE validated', $data);

        $meta = [
            'max_width' => (int)($data['image_max_width'] ?? ($client->image_meta['max_width'] ?? 300)),
            'max_height' => (int)($data['image_max_height'] ?? ($client->image_meta['max_height'] ?? 300)),
        ];

        if (!empty($data['remove_image'])) {
            $this->deleteImageIfExists($client->image_path);
            $client->image_path = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImageIfExists($client->image_path);
            $client->image_path = $this->processAndStoreImage($request->file('image'), $data['name'], $meta);
        }

        $client->name = $data['name'];
        $client->website = $data['website'] ?? null;
        $client->order = (int)$data['order'];
        $client->visible = (bool)$data['visible'];
        $client->image_meta = $meta;
        $client->save();

        return redirect()->route('panel.clients.index')->with('success', 'Cliente atualizado.');
    }

    public function destroy(Client $client)
    {
        $this->deleteImageIfExists($client->image_path);
        $client->delete();
        return redirect()->back()->with('success', 'Cliente removido.');
    }

    // ===== Helpers =====
    /**
     * Processa e salva a imagem no disco público.
     *
     * - Redimensiona mantendo proporção para caber em max_width x max_height (sem upscaling)
     * - Nome do arquivo: slug-limitado-30 + "-" + time() + "." + ext
     * - Retorna o caminho relativo dentro do disco (ex.: "clients/foo-1734020000.jpg")
     *
     * @param \Illuminate\Http\UploadedFile $uploadedFile
     * @param string $baseName  Nome base para gerar o slug
     * @param array{max_width?:int,max_height?:int} $meta
     * @return string
     */
    protected function processAndStoreImage($uploadedFile, string $baseName, array $meta): string
    {
        $disk = 'public';
        $dir = 'clients';

        // extensão original (fallback jpg)
        $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: 'jpg');

        // slug limitado a 30 caracteres
        $slug = Str::slug(pathinfo($baseName, PATHINFO_FILENAME));
        if (mb_strlen($slug) > 30) {
            $slug = rtrim(mb_substr($slug, 0, 30), '-');
        }
        if ($slug === '') {
            $slug = 'arquivo';
        }

        $filename = "{$slug}-" . time() . ".{$extension}";
        $path = "{$dir}/{$filename}";

        // Lê a imagem (v3)
        $image = Image::read($uploadedFile->getPathname());

        // Limites (default 300x300)
        $maxW = max(1, (int)($meta['max_width'] ?? 300));
        $maxH = max(1, (int)($meta['max_height'] ?? 300));

        // Reduz mantendo proporção sem aumentar (v3)
        // scaleDown limita largura/altura mantendo aspect ratio e evita upscaling
        $image->scaleDown(width: $maxW, height: $maxH);

        // Encode conforme extensão (helpers do v3)
        // Para PNG não faz sentido qualidade; para JPEG/WEBP definimos 85
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $binary = $image->toJpeg(85);
                $extension = 'jpg'; // normaliza
                break;
            case 'png':
                $binary = $image->toPng();
                break;
            case 'webp':
                $binary = $image->toWebp(85);
                break;
            default:
                // fallback para JPEG
                $binary = $image->toJpeg(85);
                $extension = 'jpg';
                // ajusta nome se precisar (mantém path consistente)
                $filename = "{$slug}-" . time() . ".{$extension}";
                $path = "{$dir}/{$filename}";
                break;
        }

        // Salva no disco público
        Storage::disk($disk)->put($path, (string) $binary);

        return $path;
    }

    protected function deleteImageIfExists(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function slugLimited(string $text): string
    {
        $slug = Str::slug($text);
        if (mb_strlen($slug) > 30) {
            $slug = mb_substr($slug, 0, 30);
            $slug = rtrim($slug, '-');
        }
        return $slug ?: 'arquivo';
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // Garante que id=1 jamais seja deletado
        $ids = collect($data['ids'])->filter(fn ($id) => (int)$id !== 0)->values();
        if ($ids->isEmpty()) {
            return back()->with('warning', 'Nenhum registro válido para exclusão.');
        }

        $items = Client::whereIn('id', $ids)->get();
        foreach ($items as $item) {
            $this->deleteImageIfExists($item->image_path);
            $item->delete();
        }

        //\App\Models\Client::whereIn('id', $ids)->delete();

        return back()->with('success', 'Registros selecionados removidos com sucesso.');
    }
}
