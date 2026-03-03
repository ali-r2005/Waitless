// app/Services/PostService.php
class PostService
{
    public function create(array $data): Post { ... }
    public function publish(Post $post): void { ... }
}

// Controller becomes thin:
class PostController extends Controller
{
    public function __construct(private PostService $postService) {}

    public function store(CreatePostRequest $request)
    {
        $post = $this->postService->create($request->validated());
        return response()->json($post, 201);
    }
}