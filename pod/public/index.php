<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Bizorca\Pod\Auth\Session;
use Bizorca\Pod\Core\Router;
use Bizorca\Pod\Controllers\{
    HomeController,
    CourseController,
    ForumController,
    TicketController,
    EventController,
    AdminController,
    ProfileController,
    MemberController,
    NotificationController,
};

// Security headers. The original sent none (its .htaccess only rewrote), and
// this server ignores .htaccess anyway. No CSP: the views load Tailwind and
// Alpine from CDNs and the lesson page embeds YouTube/Vimeo iframes.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$path = current_path();

// The SSO and sign-out routes now belong to the shared account.
if ($path === '/sso/redirect' || $path === '/sso/callback') {
    redirect('/account/login.php?next=' . rawurlencode(url('/')));
}
if ($path === '/logout') {
    redirect('/account/logout.php');
}

// Every Pod page is for signed-in members (the original sent everyone else to
// SSO). tl_require_login() redirects without creating a session, so an
// anonymous visitor gets no cookie.
tl_require_login();
if (empty(Session::user()['is_active'])) {
    // The original only checked this at SSO sign-in, so a member whose access
    // was disabled kept it until their 7-day session ran out.
    http_response_code(403);
    render('error', ['code' => 403, 'message' => 'Your access to Bizorca Pod has been disabled. Contact support if you think this is an error.']);
    exit;
}

$router = new Router();

// ─── Home ─────────────────────────────────────────────────────────────────────
$router->get('/',              [new HomeController(), 'index']);

// ─── Courses ──────────────────────────────────────────────────────────────────
$router->get('/courses',                              [new CourseController(), 'index']);
$router->get('/courses/{course}',                     [new CourseController(), 'show']);
$router->get('/courses/{course}/{lesson}',            [new CourseController(), 'lesson']);
$router->post('/courses/{course}/{lesson}/complete',  [new CourseController(), 'markComplete']);

// ─── Forum ────────────────────────────────────────────────────────────────────
$router->get('/forum',                  [new ForumController(), 'index']);
$router->get('/forum/search',           [new ForumController(), 'search']);
$router->get('/forum/new',              [new ForumController(), 'createPostForm']);
$router->post('/forum/new',             [new ForumController(), 'createPost']);
$router->get('/forum/category/{slug}',  [new ForumController(), 'category']);
$router->get('/forum/post/{id}',        [new ForumController(), 'showPost']);
$router->post('/forum/post/{id}/reply', [new ForumController(), 'reply']);
$router->post('/forum/post/{id}/react', [new ForumController(), 'react']);

// ─── Tickets ──────────────────────────────────────────────────────────────────
$router->get('/tickets',              [new TicketController(), 'index']);
$router->get('/tickets/new',          [new TicketController(), 'createForm']);
$router->post('/tickets/new',         [new TicketController(), 'create']);
$router->get('/tickets/{id}',         [new TicketController(), 'show']);
$router->post('/tickets/{id}/reply',  [new TicketController(), 'reply']);
$router->post('/tickets/{id}/close',  [new TicketController(), 'close']);
$router->post('/tickets/{id}/assign', [new TicketController(), 'assign']);

// ─── Events ───────────────────────────────────────────────────────────────────
$router->get('/events',                   [new EventController(), 'index']);
$router->get('/events/{id}',              [new EventController(), 'show']);
$router->post('/events/{id}/rsvp',        [new EventController(), 'rsvp']);
$router->post('/events/{id}/cancel-rsvp', [new EventController(), 'cancelRsvp']);

// ─── Profile ──────────────────────────────────────────────────────────────────
$profile = new ProfileController();
$router->get('/profile/edit',     fn() => $profile->edit());
$router->post('/profile/update',  fn() => $profile->update());
$router->get('/profile/{id}',     fn($id) => $profile->show($id));

// ─── Members ──────────────────────────────────────────────────────────────────
$router->get('/members', [new MemberController(), 'index']);

// ─── Notifications ────────────────────────────────────────────────────────────
$router->get('/notifications', [new NotificationController(), 'index']);

// ─── Admin ────────────────────────────────────────────────────────────────────
$admin = new AdminController();
$router->get('/admin',                               [$admin, 'dashboard']);
$router->get('/admin/courses',                       [$admin, 'courses']);
$router->post('/admin/courses',                      [$admin, 'createCourse']);
$router->post('/admin/courses/{id}/toggle',          [$admin, 'toggleCourse']);
$router->post('/admin/courses/{id}/delete',          [$admin, 'deleteCourse']);
$router->get('/admin/courses/{id}/lessons',          [$admin, 'lessonForm']);
$router->post('/admin/courses/{id}/lessons',         [$admin, 'createLesson']);
$router->post('/admin/lessons/{id}/delete',          [$admin, 'deleteLesson']);
$router->post('/admin/lessons/{id}/move-up',         [$admin, 'moveLessonUp']);
$router->post('/admin/lessons/{id}/move-down',       [$admin, 'moveLessonDown']);
$router->get('/admin/forum',                         [$admin, 'forum']);
$router->post('/admin/forum/categories',             [$admin, 'createCategory']);
$router->post('/admin/forum/categories/{id}/delete', [$admin, 'deleteCategory']);
$router->post('/admin/forum/posts/{id}/delete',      [$admin, 'deletePost']);
$router->post('/admin/forum/posts/{id}/pin',         [$admin, 'pinPost']);
$router->post('/admin/forum/posts/{id}/lock',        [$admin, 'lockPost']);
$router->get('/admin/events',                        [$admin, 'events']);
$router->post('/admin/events',                       [$admin, 'createEvent']);
$router->post('/admin/events/{id}/delete',           [$admin, 'deleteEvent']);
$router->get('/admin/events/{id}/rsvps',             fn($id) => $admin->eventRsvps($id));
$router->get('/admin/users',                         [$admin, 'users']);
$router->post('/admin/users/{id}/toggle-access',     [$admin, 'toggleAccess']);
$router->get('/admin/users/{id}/edit',               [$admin, 'editUser']);
$router->post('/admin/users/{id}/update',            [$admin, 'updateUser']);
$router->post('/admin/users/{id}/toggle-staff',      [$admin, 'toggleStaff']);
$router->post('/admin/users/{id}/notes',             fn($id) => $admin->addNote($id));
$router->post('/admin/notes/{id}/delete',            fn($id) => $admin->deleteNote($id));
$router->get('/admin/email',                         fn() => $admin->bulkEmail());
$router->post('/admin/email',                        fn() => $admin->sendBulkEmail());

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (\Throwable $e) {
    error_log('Pod: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!headers_sent()) {
        render('error', ['code' => 500, 'message' => 'Something went wrong.']);
    }
}
