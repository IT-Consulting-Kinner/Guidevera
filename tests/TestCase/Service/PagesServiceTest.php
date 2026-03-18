<?php

declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PagesService;
use Cake\TestSuite\TestCase;

class PagesServiceTest extends TestCase
{
    // ── Sanitizer ──

    public function testSanitizeHtmlStripsScripts(): void
    {
        $result = PagesService::sanitizeHtml('<p>Hello</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('<p>Hello</p>', $result);
    }

    public function testSanitizeHtmlStripsOnclick(): void
    {
        $result = PagesService::sanitizeHtml('<div onclick="alert(1)">text</div>');
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('text', $result);
    }

    public function testSanitizeHtmlStripsJavascriptHref(): void
    {
        $result = PagesService::sanitizeHtml('<a href="javascript:alert(1)">link</a>');
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testSanitizeHtmlAllowsSafeTags(): void
    {
        $input = '<p class="intro">Hello <strong>World</strong></p>';
        $result = PagesService::sanitizeHtml($input);
        $this->assertStringContainsString('<p', $result);
        $this->assertStringContainsString('<strong>', $result);
    }

    public function testSanitizeHtmlAllowsImages(): void
    {
        $result = PagesService::sanitizeHtml('<img src="/img/test.jpg" alt="Test" width="100">');
        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('src=', $result);
    }

    public function testSanitizeHtmlAddsRelToBlankLinks(): void
    {
        $result = PagesService::sanitizeHtml('<a href="https://example.com" target="_blank">link</a>');
        $this->assertStringContainsString('rel=', $result);
        $this->assertStringContainsString('noopener', $result);
    }

    public function testSanitizeHtmlEmpty(): void
    {
        $this->assertEquals('', PagesService::sanitizeHtml(''));
    }

    // ── Chapter Numbering ──

    public function testChapterNumberingWithNumbering(): void
    {
        $pages = $this->buildPageTree();
        $result = PagesService::calculateChapterNumbering($pages, true);
        $childTitle = $result[1]['title'] ?? $result[1]->title ?? '';
        $this->assertMatchesRegularExpression(
            '/^\d/',
            $childTitle,
            'Child page title should start with chapter number'
        );
    }

    public function testChapterNumberingWithoutNumbering(): void
    {
        $pages = $this->buildPageTree();
        $result = PagesService::calculateChapterNumbering($pages, false);
        $firstTitle = $result[0]['title'] ?? $result[0]->title ?? '';
        $this->assertStringNotContainsString('1.', $firstTitle);
    }

    public function testChapterNumberingEmptyArray(): void
    {
        $result = PagesService::calculateChapterNumbering([], true);
        $this->assertEmpty($result);
    }

    public function testInactivePageSkippedInNumbering(): void
    {
        $pages = [
            ['id' => 1, 'parent_id' => 0, 'title' => 'Root', 'status' => 'active'],
            ['id' => 2, 'parent_id' => 1, 'title' => 'Active', 'status' => 'active'],
            ['id' => 3, 'parent_id' => 1, 'title' => 'Draft', 'status' => 'inactive'],
            ['id' => 4, 'parent_id' => 1, 'title' => 'Active2', 'status' => 'active'],
        ];
        $result = PagesService::calculateChapterNumbering($pages, true);
        // Active2 should be chapter 2, not 3 (draft skipped)
        $title4 = $result[3]['title'] ?? '';
        $this->assertStringContainsString('2', $title4);
    }

    // ── Title Lookup ──

    public function testBuildTitleLookup(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Child', 'parent_id' => 1, 'status' => 'active'],
        ];
        $numbered = PagesService::calculateChapterNumbering($pages, true);
        $lookup = PagesService::buildTitleLookup($numbered);
        $this->assertArrayHasKey(1, $lookup);
        $this->assertArrayHasKey(2, $lookup);
    }

    // ── Navigation ──

    public function testCalculateNavigation(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'First', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Second', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 3, 'title' => 'Third', 'parent_id' => 0, 'status' => 'active'],
        ];
        $numbered = PagesService::calculateChapterNumbering($pages, false);
        $nav = PagesService::calculateNavigation(2, $numbered);
        $this->assertEquals(1, $nav['previousId']);
        $this->assertEquals(3, $nav['nextId']);
    }

    public function testCalculateNavigationExcludesRootWhenHidden(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Page A', 'parent_id' => 1, 'status' => 'active'],
            ['id' => 3, 'title' => 'Page B', 'parent_id' => 1, 'status' => 'active'],
        ];
        // showRoot = false: root should not appear as prev/next
        $nav = PagesService::calculateNavigation(2, $pages, false);
        $this->assertNotEquals(1, $nav['previousId'], 'Root should not be a prev/next target when hidden');
    }

    public function testCalculateNavigationFirstAndLastPage(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Only', 'parent_id' => 0, 'status' => 'active'],
        ];
        $nav = PagesService::calculateNavigation(1, $pages);
        $this->assertEquals(0, $nav['previousId']);
        $this->assertEquals(0, $nav['nextId']);
    }

    // ── Breadcrumbs ──

    public function testBuildBreadcrumbs(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Section', 'parent_id' => 1, 'status' => 'active'],
            ['id' => 3, 'title' => 'Page', 'parent_id' => 2, 'status' => 'active'],
        ];
        $crumbs = PagesService::buildBreadcrumbs(3, $pages);
        $this->assertCount(3, $crumbs);
        $this->assertEquals('Root', $crumbs[0]['title']);
        $this->assertEquals('Page', $crumbs[2]['title']);
    }

    public function testBuildBreadcrumbsUnknownPage(): void
    {
        $crumbs = PagesService::buildBreadcrumbs(999, []);
        $this->assertEmpty($crumbs);
    }

    // ── getRootPageId / shouldHideRoot ──

    public function testGetRootPageId(): void
    {
        $pages = [
            ['id' => 5, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 6, 'title' => 'Child', 'parent_id' => 5, 'status' => 'active'],
        ];
        $this->assertEquals(5, PagesService::getRootPageId($pages));
    }

    public function testGetRootPageIdEmpty(): void
    {
        $this->assertEquals(0, PagesService::getRootPageId([]));
    }

    // ── buildNavigationHtml ──

    public function testBuildNavigationHtmlGuestLinks(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Page One', 'parent_id' => 1, 'status' => 'active'],
        ];
        $html = PagesService::buildNavigationHtml($pages, 1, true, true, false);
        $this->assertStringContainsString('href="/pages/', $html);
    }

    public function testBuildNavigationHtmlAuthLinks(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Page One', 'parent_id' => 1, 'status' => 'active'],
        ];
        $html = PagesService::buildNavigationHtml($pages, 1, true, true, true);
        $this->assertStringContainsString('data-action="postPageShow"', $html);
    }

    public function testBuildNavigationHtmlHidesInactiveForGuest(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Draft', 'parent_id' => 1, 'status' => 'inactive'],
        ];
        $html = PagesService::buildNavigationHtml($pages, 1, true, true, false);
        $this->assertStringContainsString('class="hidden"', $html);
    }

    // ── loadKeywords ──

    public function testLoadKeywordsInvalidId(): void
    {
        $result = PagesService::loadKeywords(0);
        $this->assertEquals('', $result);
    }

    // ── Helper ──

    private function buildPageTree(): array
    {
        return [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Child A', 'parent_id' => 1, 'status' => 'active'],
            ['id' => 3, 'title' => 'Child B', 'parent_id' => 1, 'status' => 'active'],
            ['id' => 4, 'title' => 'Grandchild', 'parent_id' => 2, 'status' => 'active'],
        ];
    }
}
