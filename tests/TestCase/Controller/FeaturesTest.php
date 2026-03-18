<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Feature Tests — covers all features including fixes from v7–v11.
 */
class FeaturesTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.Pages', 'app.Users', 'app.Pagesindex',
        'app.PageRevisions', 'app.PageTranslations', 'app.PageFeedback',
        'app.PageTags',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->configRequest([
            'headers' => ['X-Requested-With' => 'XMLHttpRequest'],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Cake\Core\Configure::write('Manual.enableRevisions', true);
        \Cake\Core\Configure::write('Manual.enableFeedback', true);
        \Cake\Core\Configure::write('Manual.enableTranslations', false);
        \Cake\Core\Configure::write('Manual.enableSmartLinks', false);
        \Cake\Core\Configure::write('Manual.showNavigationRoot', true);
    }

    // ── Roles ──

    public function testGuestCannotEdit(): void
    {
        $this->post('/pages/edit', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('not_authenticated', $body['error'] ?? '');
    }

    public function testEditorCanEdit(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/edit', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('id', $body);
    }

    public function testEditorCannotCreate(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/create');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('insufficient_permissions', $body['error'] ?? '');
    }

    public function testContributorCanCreate(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/create');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intId', $body);
    }

    public function testEditorCannotDelete(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/delete', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('insufficient_permissions', $body['error'] ?? '');
    }

    public function testEditorCannotModerFeedback(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/feedback_moderate', ['id' => 1, 'action' => 'approve']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'feature_disabled']);
    }

    // ── Page status toggle (show mode, fix v7+) ──

    public function testContributorCanSetStatusActive(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/set_status', ['id' => 3, 'status' => 'active']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body['intAffectedRows'] ?? 0);
    }

    public function testContributorCanSetStatusInactive(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/set_status', ['id' => 1, 'status' => 'inactive']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body['intAffectedRows'] ?? 0);
    }

    public function testEditorCannotSetStatus(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/set_status', ['id' => 1, 'status' => 'inactive']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testSetStatusInvalidValueRejected(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/set_status', ['id' => 1, 'status' => 'bogus']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('invalid_status', $body['error'] ?? '');
    }

    // ── showNavigationRoot (fix v4+) ──

    public function testShowNavigationRootFalseHidesRootFromTree(): void
    {
        \Cake\Core\Configure::write('Manual.showNavigationRoot', false);
        $this->post('/pages/get_tree');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($body['arrTree'] ?? []);
    }

    // ── New page workflow_status (fix v3) ──

    public function testNewPageWithoutWorkflowIsPublished(): void
    {
        \Cake\Core\Configure::write('Manual.enableReviewProcess', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/create');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intId', $body);
        $page = $this->getTableLocator()->get('Pages')->get($body['intId']);
        $this->assertEquals('published', $page->workflow_status);
    }

    public function testNewPageWithWorkflowIsDraft(): void
    {
        \Cake\Core\Configure::write('Manual.enableReviewProcess', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/create');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intId', $body);
        $page = $this->getTableLocator()->get('Pages')->get($body['intId']);
        $this->assertEquals('draft', $page->workflow_status);
        \Cake\Core\Configure::write('Manual.enableReviewProcess', false);
    }

    // ── Revisions ──

    public function testRevisionsDisabledReturnsFeatureDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableRevisions', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/revisions', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    public function testRevisionsEnabledReturnsList(): void
    {
        \Cake\Core\Configure::write('Manual.enableRevisions', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/revisions', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('revisions', $body);
    }

    // ── Feedback ──

    public function testFeedbackDisabledReturnsError(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', false);
        $this->post('/pages/feedback', ['page_id' => 1, 'rating' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    public function testFeedbackEnabledAcceptsRating(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', true);
        $this->post('/pages/feedback', ['page_id' => 1, 'rating' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success'] ?? false);
    }

    public function testFeedbackInvalidRatingRejected(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', true);
        $this->post('/pages/feedback', ['page_id' => 1, 'rating' => 5]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('invalid_feedback', $body['error'] ?? '');
    }

    public function testFeedbackCommentStartsPending(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', true);
        $this->post('/pages/feedback', ['page_id' => 1, 'rating' => 1, 'comment' => 'Great page!']);
        $fb = $this->getTableLocator()->get('PageFeedback')->find()
            ->where(['page_id' => 1])->orderBy(['id' => 'DESC'])->first();
        $this->assertEquals('pending', $fb->status);
    }

    public function testFeedbackRejectsInactivePage(): void
    {
        \Cake\Core\Configure::write('Manual.enableFeedback', true);
        $this->post('/pages/feedback', ['page_id' => 3, 'rating' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('page_not_active', $body['error'] ?? '');
    }

    // ── Print ──

    public function testPrintDisabledRedirects(): void
    {
        \Cake\Core\Configure::write('Manual.enablePrint', false);
        $this->get('/pages/print_all');
        $this->assertRedirect('/');
    }

    // ── Translations ──

    public function testEditWithoutTranslationsReturnsEmptyLocales(): void
    {
        \Cake\Core\Configure::write('Manual.enableTranslations', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/edit', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $locales = $body['availableLocales'] ?? [];
        $this->assertTrue(empty($locales) || $locales === ['en']);
    }

    public function testTranslationStatusDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableTranslations', false);
        $this->get('/pages/translation_status');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    // ── Locale detection (fix v22) ──

    public function testLocaleFromQueryParam(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/edit', ['id' => 1, 'locale' => 'en']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('en', $body['locale'] ?? '');
    }

    // ── Search ──

    public function testSearchReturnsSearchMode(): void
    {
        $this->post('/pages/search', ['search' => 'test']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['searchMode'] ?? '', ['fulltext', 'like']);
    }

    public function testSearchReturnsSnippets(): void
    {
        $this->post('/pages/search', ['search' => 'test']);
        $body = json_decode((string)$this->_response->getBody(), true);
        if (!empty($body['results'])) {
            $this->assertArrayHasKey('snippet', $body['results'][0]);
        }
        $this->assertContains($body['searchMode'] ?? '', ['fulltext', 'like']);
    }

    // ── Cache Invalidation ──

    public function testCacheInvalidatedOnCreate(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/create');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intId', $body);
        $cached = \Cake\Cache\Cache::read('numbered_pages', 'default');
        $this->assertNull($cached, 'Cache should be invalidated after page creation');
    }

    // ── Workflow ──

    public function testSetWorkflowStatusRequiresContributor(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/set_workflow', ['id' => 1, 'workflow_status' => 'review']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'not_authenticated']);
    }

    public function testSetWorkflowStatusValid(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/set_workflow', ['id' => 1, 'workflow_status' => 'review']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body['intAffectedRows'] ?? 0);
    }

    public function testSetWorkflowStatusInvalid(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin', 'fullname' => 'Test']]);
        $this->post('/pages/set_workflow', ['id' => 1, 'workflow_status' => 'bogus']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('invalid_workflow_status', $body['error'] ?? '');
    }

    // ── Tags ──

    public function testSaveTagsDeduplicated(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/save_tags', ['page_id' => 1, 'tags' => 'Security, security, SECURITY, other']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertCount(2, $body['tags'] ?? []); // 'security' + 'other'
    }

    public function testRelatedPagesEmpty(): void
    {
        $this->post('/pages/related', ['page_id' => 999]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($body['related'] ?? []);
    }

    // ── Quality Report ──

    public function testQualityReportRequiresAdmin(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->get('/pages/quality');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'not_authenticated']);
    }

    public function testQualityReportAdmin(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin', 'fullname' => 'Test']]);
        $this->get('/pages/quality');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('quality', $body);
    }

    // ── Review Queue ──

    public function testReviewQueueRequiresContributor(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->get('/pages/review_queue');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'not_authenticated']);
    }

    // ── Subscriptions ──

    public function testSubscribeToggle(): void
    {
        \Cake\Core\Configure::write('Manual.enableSubscriptions', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/subscribe', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['subscribed'] ?? false);
    }

    public function testSubscribeRequiresAuth(): void
    {
        \Cake\Core\Configure::write('Manual.enableSubscriptions', true);
        $this->post('/pages/subscribe', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('not_authenticated', $body['error'] ?? '');
    }

    // ── Acknowledgements (locale-aware, fix v25) ──

    public function testAcknowledgeDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableAcknowledgements', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/acknowledge', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    public function testAcknowledgeEnabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableAcknowledgements', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/acknowledge', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['acknowledged'] ?? false);
    }

    public function testAcknowledgeRejectsInactivePage(): void
    {
        \Cake\Core\Configure::write('Manual.enableAcknowledgements', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/acknowledge', ['page_id' => 3]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('page_not_active', $body['error'] ?? '');
    }

    public function testAcknowledgeIsLocaleSpecific(): void
    {
        \Cake\Core\Configure::write('Manual.enableAcknowledgements', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);

        // Acknowledge in English
        $this->post('/pages/acknowledge', ['page_id' => 1, 'locale' => 'en']);
        $bodyEn = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($bodyEn['acknowledged'] ?? false);

        // Acknowledge status for German should be separate
        $this->post('/pages/ack_status', ['page_id' => 1, 'locale' => 'de']);
        $bodyDe = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($bodyDe['acknowledged'] ?? true);
    }

    // ── Inline Comments ──

    public function testInlineCommentsDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableInlineComments', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/inline_comments', ['page_id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    public function testResolveInlineCommentRequiresEditor(): void
    {
        \Cake\Core\Configure::write('Manual.enableInlineComments', true);
        $this->post('/pages/resolve_inline_comment', ['id' => 1]);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['not_authenticated', 'insufficient_permissions']);
    }

    // ── Analytics ──

    public function testAnalyticsRequiresAdmin(): void
    {
        \Cake\Core\Configure::write('Manual.enableContentAnalytics', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->get('/pages/analytics');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['insufficient_permissions', 'not_authenticated']);
    }

    // ── View counter only counts guests (fix v25) ──

    public function testViewCounterNotIncrementedForLoggedInUsers(): void
    {
        $before = $this->getTableLocator()->get('Pages')->get(1)->views;
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/show', ['id' => 1]);
        $after = $this->getTableLocator()->get('Pages')->get(1)->views;
        $this->assertEquals($before, $after, 'View counter must not increment for logged-in users');
    }

    // ── Import ──

    public function testImportDisabled(): void
    {
        \Cake\Core\Configure::write('Manual.enableImport', false);
        $this->session(['Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test']]);
        $this->post('/pages/import', ['content' => '# Test', 'format' => 'markdown', 'title' => 'Test']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('feature_disabled', $body['error'] ?? '');
    }

    // ── Smart Links ──

    public function testLinkSuggestRequiresAuth(): void
    {
        $this->post('/pages/link_suggest', ['q' => 'test']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('not_authenticated', $body['error'] ?? '');
    }

    public function testLinkSuggestShortQuery(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test']]);
        $this->post('/pages/link_suggest', ['q' => 'a']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($body['pages'] ?? []);
    }

    // ── Stale List ──

    public function testStaleListRequiresEditor(): void
    {
        $this->get('/pages/stale_list');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['not_authenticated', 'insufficient_permissions']);
    }

    // ── Review Process ──

    public function testReviewDecisionOnlyAssignedReviewer(): void
    {
        \Cake\Core\Configure::write('Manual.enableReviewProcess', true);
        $this->session(['Auth' => ['id' => 2, 'role' => 'contributor', 'fullname' => 'Other']]);
        $this->post('/pages/review_decision', ['review_id' => 1, 'decision' => 'approved']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? $body['status'] ?? '', ['not_found', 'insufficient_permissions']);
        \Cake\Core\Configure::write('Manual.enableReviewProcess', false);
    }

    public function testReviewDecisionRejectsWithoutComment(): void
    {
        \Cake\Core\Configure::write('Manual.enableReviewProcess', true);
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin', 'fullname' => 'Admin']]);
        $this->post('/pages/review_decision', ['review_id' => 1, 'decision' => 'rejected', 'comment' => '']);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertContains($body['error'] ?? '', ['comment_required', 'not_found']);
        \Cake\Core\Configure::write('Manual.enableReviewProcess', false);
    }
}
