<?php
declare(strict_types=1);

namespace Wiz\Controllers\Admin;

use InvalidArgumentException;
use Throwable;
use Wiz\Core\Auth;
use Wiz\Core\RedirectException;
use Wiz\Core\Request;
use Wiz\Core\Response;
use Wiz\Core\Session;
use Wiz\Core\View;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Services\EmailTemplateCatalog;
use Wiz\Services\EmailTemplateService;

/**
 * The email design studio. Administrator-only, draft-first, and it never sends
 * an email: saving and publishing only change which design a future message
 * uses. Cloned from Bulbula.et's `Staff\EmailTemplateController`.
 */
final class EmailTemplateController
{
    public function __construct(
        private readonly View $view,
        private readonly EmailTemplateService $templates,
        private readonly ActivityLogRepository $activity,
        private readonly Auth $auth,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $key = $request->queryString('type', array_key_first(EmailTemplateCatalog::LABELS) ?: '');

        if (!isset(EmailTemplateCatalog::LABELS[$key])) {
            throw new RedirectException('/admin/email-templates');
        }

        $rows = $this->templates->revisions($key);
        $active = $this->templates->activeId($key);
        $selected = (int) $request->queryString('revision', '0');
        $revision = null;

        foreach ($rows as $row) {
            if (($selected > 0 && (int) $row['id'] === $selected) || ($selected === 0 && (int) $row['id'] === $active)) {
                $revision = $row;
                break;
            }
        }

        return $this->view->render('admin/email-templates/index', [
            'title' => 'Email designs',
            'heading' => 'Email designs',
            'subheading' => 'Choose a message, design its layout, save a draft and publish only when it is right.',
            'active' => 'email-templates',
            'head' => '<link rel="stylesheet" href="' . e(asset('email-editor.css')) . '">'
                . '<link rel="stylesheet" href="' . e(asset('email-templates.css')) . '">',
            'scripts' => '<script src="' . e(asset('email-editor.js')) . '" defer></script>',
            'key' => $key,
            'catalog' => $this->templates->list(),
            'revisions' => $rows,
            'activeId' => $active,
            'selectedRevision' => $revision,
            'initial' => EmailTemplateCatalog::initial($key),
            'requiredToken' => '{{' . EmailTemplateCatalog::required($key) . '}}',
            'isCode' => EmailTemplateCatalog::isCode($key),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, array $params = []): Response
    {
        $key = $request->string('template_key');

        if (!isset(EmailTemplateCatalog::LABELS[$key])) {
            throw new RedirectException('/admin/email-templates');
        }

        $actor = (int) $this->auth->id();
        $error = null;
        $id = 0;

        try {
            $id = $this->templates->saveDraft(
                $key,
                $request->string('subject'),
                $request->string('html'),
                $request->string('text'),
                $request->string('project'),
                $actor,
            );

            $this->activity->record($actor, 'email_template.draft_saved', 'email_template', $id, ['type' => $key], $request->ip());
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (Throwable) {
            $error = 'Could not safely save this design. No active email was changed.';
        }

        if ($error !== null) {
            $this->session->flash('error', $error);

            throw new RedirectException('/admin/email-templates?type=' . rawurlencode($key));
        }

        $this->session->flash('success', 'Draft saved. It will not affect outgoing emails until you publish it.');

        throw new RedirectException('/admin/email-templates?type=' . rawurlencode($key) . '&revision=' . $id);
    }

    /**
     * @param array<string, string> $params
     */
    public function publish(Request $request, array $params = []): Response
    {
        $key = $request->string('template_key');

        if (!isset(EmailTemplateCatalog::LABELS[$key])) {
            throw new RedirectException('/admin/email-templates');
        }

        $revision = (int) $request->string('revision_id', '0');
        $actor = (int) $this->auth->id();

        try {
            $this->templates->publish($key, $revision);
            $this->activity->record($actor, 'email_template.published', 'email_template', $revision, ['type' => $key], $request->ip());
            $this->session->flash('success', 'Published. Future emails of this type use this design; older revisions stay available for rollback.');
        } catch (Throwable) {
            $this->session->flash('error', 'Could not publish this revision; the current design is unchanged.');
        }

        throw new RedirectException('/admin/email-templates?type=' . rawurlencode($key) . '&revision=' . $revision);
    }
}
