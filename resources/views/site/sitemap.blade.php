{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach([route('home'),route('articles.index'),route('news.index'),route('insights.index'),route('case-studies.index'),route('research.index'),route('glossary.index'),route('academy.index'),route('tools.index'),route('resources'),route('about'),route('assess.start')] as $url)<url><loc>{{ $url }}</loc></url>@endforeach
@foreach($articles as $article)<url><loc>{{ route('articles.show',$article) }}</loc><lastmod>{{ ($article->last_reviewed_at ?? $article->updated_at)->toAtomString() }}</lastmod></url>@endforeach
@foreach($terms as $term)<url><loc>{{ route('glossary.show',$term) }}</loc><lastmod>{{ $term->updated_at->toAtomString() }}</lastmod></url>@endforeach
@foreach($topics as $topic)<url><loc>{{ route('topics.show',$topic->slug) }}</loc></url>@endforeach
@foreach($tags as $tag)<url><loc>{{ route('tags.show',$tag) }}</loc></url>@endforeach
@foreach($authors as $author)<url><loc>{{ route('authors.show',$author) }}</loc></url>@endforeach
@foreach($courses as $course)<url><loc>{{ route('academy.show',$course) }}</loc><lastmod>{{ $course->updated_at->toAtomString() }}</lastmod></url>@foreach($course->lessons as $lesson)@if($lesson->isPublished())<url><loc>{{ route('academy.lesson',[$course,$lesson]) }}</loc><lastmod>{{ $lesson->updated_at->toAtomString() }}</lastmod></url>@endif @endforeach @endforeach
@foreach($tools as $tool)<url><loc>{{ \App\Support\Markdown::toolUrl($tool) }}</loc><lastmod>{{ $tool->updated_at->toAtomString() }}</lastmod></url>@endforeach
</urlset>
