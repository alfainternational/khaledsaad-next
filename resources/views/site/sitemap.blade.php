{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach([route('home'),route('articles.index'),route('insights.index'),route('academy.index'),route('tools.index'),route('resources'),route('about'),route('assess.start')] as $url)<url><loc>{{ $url }}</loc></url>@endforeach
@foreach($articles as $article)<url><loc>{{ route('articles.show',$article) }}</loc><lastmod>{{ $article->updated_at->toAtomString() }}</lastmod></url>@endforeach
@foreach($courses as $course)<url><loc>{{ route('academy.show',$course) }}</loc><lastmod>{{ $course->updated_at->toAtomString() }}</lastmod></url>@foreach($course->lessons as $lesson)@if($lesson->isPublished())<url><loc>{{ route('academy.lesson',[$course,$lesson]) }}</loc><lastmod>{{ $lesson->updated_at->toAtomString() }}</lastmod></url>@endif @endforeach @endforeach
@foreach($tools as $tool)<url><loc>{{ route('tools.show',$tool) }}</loc><lastmod>{{ $tool->updated_at->toAtomString() }}</lastmod></url>@endforeach
</urlset>
