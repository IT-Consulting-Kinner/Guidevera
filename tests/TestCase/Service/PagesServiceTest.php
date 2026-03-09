<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PagesService;
use Cake\TestSuite\TestCase;

class PagesServiceTest extends TestCase
{
    // ── Sanitizer Tests ──

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

    // ── Chapter Numbering Tests ──

    public function testChapterNumberingWithNumbering(): void
    {
        $pages = $this->_buildPageTree();
        $result = PagesService::calculateChapterNumbering($pages, true);
        // Root page should have chapter number
        $this->assertStringContainsString('1', $result[0]['title'] ?? $result[0]->title ?? '');
    }

    public function testChapterNumberingWithoutNumbering(): void
    {
        $pages = $this->_buildPageTree();
        $result = PagesService::calculateChapterNumbering($pages, false);
        // Without numbering, title should not have chapter prefix
        $firstTitle = $result[0]['title'] ?? $result[0]->title ?? '';
        $this->assertStringNotContainsString('1.', $firstTitle);
    }

    public function testChapterNumberingEmptyArray(): void
    {
        $result = PagesService::calculateChapterNumbering([], true);
        $this->assertEmpty($result);
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
        $this->assertArrayHasKey('previousId', $nav);
        $this->assertArrayHasKey('nextId', $nav);
    }

    // ── buildNavigationHtml ──

    public function testBuildNavigationHtmlGuestLinks(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Page One', 'parent_id' => 1, 'status' => 'active'],
        ];
        $html = PagesService::buildNavigationHtml($pages, 1, true, true, false);
        // Guest: should have real href, not javascript:
        $this->assertStringContainsString('href="/pages/', $html);
        $this->assertStringNotContainsString('onclick="post_page_show', $html);
    }

    public function testBuildNavigationHtmlAuthLinks(): void
    {
        $pages = [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Page One', 'parent_id' => 1, 'status' => 'active'],
        ];
        $html = PagesService::buildNavigationHtml($pages, 1, true, true, true);
        // Auth: should have javascript onclick
        $this->assertStringContainsString('onclick="post_page_show', $html);
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

    private function _buildPageTree(): array
    {
        return [
            ['id' => 1, 'title' => 'Root', 'parent_id' => 0, 'status' => 'active'],
            ['id' => 2, 'title' => 'Child A', 'parent_id' => 1, 'status' => 'active'],
            ['id' => 3, 'title' => 'Child B', 'parent_id' => 1, 'status' => 'active'],
            ['id' => 4, 'title' => 'Grandchild', 'parent_id' => 2, 'status' => 'active'],
        ];
    }
}
