<?php

namespace Tests\Unit;

use App\Domain\Content\Models\ContentItem;
use App\Domain\Lab\Services\LabCatalog;
use App\Support\FrontMatter;
use App\Support\Markdown;
use Database\Seeders\AcademySeeder;
use Database\Seeders\CompleteAcademyContent;
use Database\Seeders\MagazineSeeder;
use Tests\TestCase;

class EditorialSourceTest extends TestCase
{
    public function test_editorial_sources_match_the_real_content_and_application_catalogs(): void
    {
        $tools = array_column(LabCatalog::tools(), 'slug');
        $topics = array_column(AcademySeeder::COURSES, 0);
        $categories = array_column(MagazineSeeder::CATEGORIES, 'slug');
        $lessons = collect(CompleteAcademyContent::courses())->flatMap(fn ($course) => array_column($course['lessons'], 'slug'))->all();
        $seen = [];
        foreach (['magazine', 'editorial'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(database_path('content/'.$directory), \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'md') {
                    continue;
                }
                [$attrs, $body] = FrontMatter::parse(file_get_contents($file->getPathname()));
                $slug = $attrs['slug'];
                $this->assertNotContains($slug, $seen);
                $seen[] = $slug;
                $this->assertSame(ContentItem::TYPE_SECTION[$attrs['type']], $attrs['section'], $slug);
                $this->assertContains($attrs['category'], $categories, $slug);
                $this->assertContains($attrs['topic'], $topics, $slug);
                $this->assertContains($attrs['tool'], $tools, $slug);
                $this->assertNotEmpty($attrs['tldr'], $slug);
                $this->assertGreaterThanOrEqual(3, count(Markdown::headings($body)), $slug);
                if ($directory === 'editorial') {
                    $this->assertContains($attrs['lesson'], $lessons, $slug);
                }
                if (in_array($attrs['type'], ContentItem::SOURCED_TYPES, true)) {
                    $this->assertNotEmpty($attrs['sources'], $slug);
                }
                foreach ($attrs['sources'] ?? [] as $source) {
                    $this->assertSame('https', parse_url($source['url'], PHP_URL_SCHEME), $slug);
                    $this->assertNotEmpty($source['title'], $slug);
                }
            }
        }
        $this->assertCount(59, $seen);
    }
}
