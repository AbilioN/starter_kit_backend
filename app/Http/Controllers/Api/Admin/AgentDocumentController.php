<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\AgentDocument\SaveAgentDocumentUseCase;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Domain\Exceptions\DocumentExtractionException;
use App\Domain\Services\DocumentTextExtractorInterface;
use App\Http\Controllers\Controller;
use App\Models\AgentDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The knowledge a tenant gives its assistant.
 *
 * `agent_documents` shipped with two search tools and no way to write to it —
 * the only writer was a seeder — so this is the missing half.
 *
 * Reads need `document-read`, writes need `document-manage`. They are split
 * because a write decides `audience`, and getting that wrong publishes a
 * supplier contract to every end user the tenant serves.
 */
class AgentDocumentController extends Controller
{
    public function __construct(
        private AuthorizeActionUseCase $authorize,
        private SaveAgentDocumentUseCase $save,
        private DocumentTextExtractorInterface $extractor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize->execute(AdminFactory::createFromModel($request->user()), 'document-read');

        $documents = AgentDocument::query()
            ->orderBy('title')
            // `content` is a longText holding an entire manual. A list screen
            // needs its length, never its body.
            ->get(['id', 'title', 'description', 'audience', 'is_active', 'file_path', 'updated_at'])
            ->map(fn (AgentDocument $d) => $this->present($d));

        return response()->json(['success' => true, 'data' => $documents]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorize->execute(AdminFactory::createFromModel($request->user()), 'document-read');

        $document = AgentDocument::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->present($document) + ['content' => $document->content],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize->execute(AdminFactory::createFromModel($request->user()), 'document-manage');

        $validated = $this->validated($request);

        $document = $this->save->execute($validated, $request->file('file'));

        return response()->json(['success' => true, 'data' => $this->present($document)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize->execute(AdminFactory::createFromModel($request->user()), 'document-manage');

        $document = AgentDocument::findOrFail($id);
        $validated = $this->validated($request);

        $document = $this->save->execute($validated, $request->file('file'), $document);

        return response()->json(['success' => true, 'data' => $this->present($document)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorize->execute(AdminFactory::createFromModel($request->user()), 'document-manage');

        $document = AgentDocument::findOrFail($id);

        // The uploaded PDF goes with it. Without this the bytes stay on disk
        // and the `files` row stays in the tenant's quota and in every backup,
        // with nothing in this module able to reach either again.
        $this->save->discardFiles($document);

        $document->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

    /**
     * Validated here rather than in a FormRequest because the mime allowlist is
     * asked of the extractor rather than written down twice — a list the
     * request believed and the parser did not would be a rejection nobody could
     * explain, or worse an acceptance.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $mimes = implode(',', $this->extractor->supportedMimeTypes());

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'audience' => ['nullable', Rule::in(AgentDocument::AUDIENCES)],
            'is_active' => ['sometimes', 'boolean'],
            // One or the other: text pasted in, or a file to read it from.
            'content' => ['nullable', 'string'],
            'file' => [
                'nullable',
                'file',
                // An allowlist, unlike UploadFileRequest — which has none, and
                // is the reason docs/20 gated this part on the file subsystem.
                'mimetypes:'.$mimes,
                // Belt AND braces. A .php file containing prose is detected as
                // text/plain and would clear the mime rule on its own.
                'extensions:'.implode(',', $this->extractor->supportedExtensions()),
                'max:20480',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(AgentDocument $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'description' => $document->description,
            'audience' => $document->audience,
            'is_active' => (bool) $document->is_active,
            'has_file' => $document->file_path !== null,
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }
}
