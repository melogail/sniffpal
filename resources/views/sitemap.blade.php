<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($pages as $page)
    <url><loc>{{ $page }}</loc></url>
@endforeach
@foreach ($pets as $pet)
    <url><loc>{{ route('pets.show', $pet) }}</loc><lastmod>{{ $pet->updated_at->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
