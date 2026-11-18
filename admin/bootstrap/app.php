<?php
declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Builds the service container, registers every service as a singleton and
 * returns the configured App instance. Nothing in here touches the database
 * until something asks for it — the CLI, tests and error pages can run without
 * a live connection.
 *
 * Every binding is written out explicitly. There is no autowiring: a class that
 * is not registered here cannot be resolved, which makes the dependency graph
 * readable in one screen.
 */

use Wiz\Controllers\Admin\AccountController;
use Wiz\Controllers\Admin\AuthController;
use Wiz\Controllers\Admin\ActivityController;
use Wiz\Controllers\Admin\BookingController;
use Wiz\Controllers\Admin\CategoryController;
use Wiz\Controllers\Admin\DashboardController;
use Wiz\Controllers\Admin\EmailTemplateController;
use Wiz\Controllers\Admin\GalleryController;
use Wiz\Controllers\Admin\WebsiteImagesController;
use Wiz\Controllers\Admin\MessageController;
use Wiz\Controllers\Admin\ReviewController;
use Wiz\Controllers\Admin\ScheduleController;
use Wiz\Controllers\Admin\SettingsController;
use Wiz\Controllers\Admin\TermsController;
use Wiz\Controllers\Admin\UserController;
use Wiz\Controllers\Api\BookingApiController;
use Wiz\Controllers\Api\ContentApiController;
use Wiz\Controllers\Api\MessageApiController;
use Wiz\Controllers\PublicBookingController;
use Wiz\Core\App;
use Wiz\Core\Auth;
use Wiz\Core\Config;
use Wiz\Core\Csrf;
use Wiz\Core\Database;
use Wiz\Core\Env;
use Wiz\Core\Logger;
use Wiz\Core\Middleware\AdminOnlyMiddleware;
use Wiz\Core\Middleware\AuthMiddleware;
use Wiz\Core\Middleware\CsrfMiddleware;
use Wiz\Core\Middleware\GuestMiddleware;
use Wiz\Core\Session;
use Wiz\Core\View;
use Wiz\Domain\Booking\SlotCalculator;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\BookingRepository;
use Wiz\Repositories\BookingSlotRepository;
use Wiz\Repositories\BusinessHoursRepository;
use Wiz\Repositories\CategoryRepository;
use Wiz\Repositories\ClosureRepository;
use Wiz\Repositories\SiteImageRepository;
use Wiz\Repositories\MessageRepository;
use Wiz\Repositories\RateLimitRepository;
use Wiz\Repositories\SettingsRepository;
use Wiz\Repositories\TermsRepository;
use Wiz\Services\TermsService;
use Wiz\Repositories\GalleryRepository;
use Wiz\Repositories\LoginAttemptRepository;
use Wiz\Repositories\ReviewRepository;
use Wiz\Repositories\ServiceRepository;
use Wiz\Repositories\UserRepository;
use Wiz\Core\Mailer;
use Wiz\Infrastructure\PhpMailerTransport;
use Wiz\Contracts\MailTransport;
use Wiz\Services\AuthService;
use Wiz\Services\TeamService;
use Wiz\Services\AvailabilityService;
use Wiz\Services\BookingService;
use Wiz\Services\BookingNotifier;
use Wiz\Services\EmailTemplateService;
use Wiz\Services\Uploader;
use Wiz\Support\ChangePolicy;
use Wiz\Support\MailSettings;
use Wiz\Support\MediaUsage;
use Wiz\Support\SalonDetails;
use Wiz\Support\SignedLink;

$root = dirname(__DIR__);

require $root . '/bootstrap/autoload.php';

Env::load($root . '/.env');

$app = new App($root);

// ------------------------------------------------------------------- core
$app->singleton(Config::class, static fn (App $a): Config => new Config($a->basePath('config')));

$app->singleton(Logger::class, static fn (App $a): Logger => new Logger($a->basePath('storage/logs')));

$app->singleton(Database::class, static fn (App $a): Database => new Database(
    $a->config('database') ?? [],
));

$app->singleton(Session::class, static fn (App $a): Session => new Session($a->config('app') ?? []));

$app->singleton(Csrf::class, static fn (App $a): Csrf => new Csrf($a->get(Session::class)));

$app->singleton(View::class, static fn (App $a): View => new View($a->basePath('src/Views')));

// ----------------------------------------------------------- data access
$app->singleton(UserRepository::class, static fn (App $a): UserRepository => new UserRepository(
    $a->get(Database::class),
));

$app->singleton(LoginAttemptRepository::class, static fn (App $a): LoginAttemptRepository => new LoginAttemptRepository(
    $a->get(Database::class),
));

$app->singleton(ActivityLogRepository::class, static fn (App $a): ActivityLogRepository => new ActivityLogRepository(
    $a->get(Database::class),
));

$app->singleton(CategoryRepository::class, static fn (App $a): CategoryRepository => new CategoryRepository(
    $a->get(Database::class),
));

$app->singleton(ServiceRepository::class, static fn (App $a): ServiceRepository => new ServiceRepository(
    $a->get(Database::class),
));

$app->singleton(GalleryRepository::class, static fn (App $a): GalleryRepository => new GalleryRepository(
    $a->get(Database::class),
));

$app->singleton(ReviewRepository::class, static fn (App $a): ReviewRepository => new ReviewRepository(
    $a->get(Database::class),
));

$app->singleton(TermsRepository::class, static fn (App $a): TermsRepository => new TermsRepository(
    $a->get(Database::class),
));

$app->singleton(SettingsRepository::class, static fn (App $a): SettingsRepository => new SettingsRepository(
    $a->get(Database::class),
));

$app->singleton(BusinessHoursRepository::class, static fn (App $a): BusinessHoursRepository => new BusinessHoursRepository(
    $a->get(Database::class),
));

$app->singleton(ClosureRepository::class, static fn (App $a): ClosureRepository => new ClosureRepository(
    $a->get(Database::class),
));

$app->singleton(BookingSlotRepository::class, static fn (App $a): BookingSlotRepository => new BookingSlotRepository(
    $a->get(Database::class),
));

$app->singleton(BookingRepository::class, static fn (App $a): BookingRepository => new BookingRepository(
    $a->get(Database::class),
));

$app->singleton(MessageRepository::class, static fn (App $a): MessageRepository => new MessageRepository(
    $a->get(Database::class),
));

$app->singleton(RateLimitRepository::class, static fn (App $a): RateLimitRepository => new RateLimitRepository(
    $a->get(Database::class),
));

// -------------------------------------------------------------- services
$app->singleton(Auth::class, static fn (App $a): Auth => new Auth(
    $a->get(Session::class),
    $a->get(UserRepository::class),
));

$app->singleton(SlotCalculator::class, static fn (App $a): SlotCalculator => new SlotCalculator());

$app->singleton(AvailabilityService::class, static fn (App $a): AvailabilityService => new AvailabilityService(
    $a->get(BusinessHoursRepository::class),
    $a->get(ClosureRepository::class),
    $a->get(BookingSlotRepository::class),
    $a->get(SettingsRepository::class),
    $a->get(SlotCalculator::class),
));

$app->singleton(BookingService::class, static fn (App $a): BookingService => new BookingService(
    $a->get(Database::class),
    $a->get(BookingRepository::class),
    $a->get(BookingSlotRepository::class),
    $a->get(ServiceRepository::class),
    $a->get(AvailabilityService::class),
    $a->get(SlotCalculator::class),
    $a->get(ActivityLogRepository::class),
    $a->get(Logger::class),
    $a->get(BookingNotifier::class),
    $a->get(CategoryRepository::class),
));

$app->singleton(AuthService::class, static fn (App $a): AuthService => new AuthService(
    $a->get(UserRepository::class),
    $a->get(LoginAttemptRepository::class),
    $a->get(ActivityLogRepository::class),
    $a->get(Auth::class),
    $a->get(Logger::class),
    (int) ($a->config('app.login_max_attempts') ?? 5),
    (int) ($a->config('app.login_lockout_minutes') ?? 15),
    (int) ($a->config('app.login_max_attempts_per_ip') ?? 25),
));

$app->singleton(SignedLink::class, static fn (App $a): SignedLink => new SignedLink(
    (string) ($a->config('app.key') ?? ''),
));

$app->singleton(SalonDetails::class, static fn (App $a): SalonDetails => new SalonDetails(
    $a->get(SettingsRepository::class),
));

$app->singleton(ChangePolicy::class, static fn (App $a): ChangePolicy => new ChangePolicy(
    $a->get(SettingsRepository::class),
    (string) ($a->config('app.timezone') ?? 'Africa/Addis_Ababa'),
));

$app->singleton(TermsService::class, static fn (App $a): TermsService => new TermsService(
    $a->get(TermsRepository::class),
    $a->get(SettingsRepository::class),
    $a->get(ChangePolicy::class),
));

$app->singleton(MailSettings::class, static fn (App $a): MailSettings => new MailSettings(
    $a->get(SettingsRepository::class),
    is_array($a->config('mail')) ? $a->config('mail') : [],
    (string) ($a->config('app.key') ?? ''),
));

$app->singleton(Mailer::class, static fn (App $a): Mailer => new Mailer(
    $a->get(MailSettings::class)->config(),
    $a->get(Logger::class),
));

$app->singleton(MailTransport::class, static fn (App $a): MailTransport => new PhpMailerTransport(
    $a->get(Mailer::class),
));

$app->singleton(EmailTemplateService::class, static fn (App $a): EmailTemplateService => new EmailTemplateService(
    $a->get(Database::class),
    (string) ($a->config('app.url') ?? ''),
));

$app->singleton(BookingNotifier::class, static fn (App $a): BookingNotifier => new BookingNotifier(
    $a->get(MailTransport::class),
    $a->get(EmailTemplateService::class),
    $a->get(View::class),
    $a->get(SignedLink::class),
    $a->get(Logger::class),
    (string) ($a->config('app.url') ?? ''),
    $a->get(MailSettings::class)->salonEmail(),
    (string) ($a->config('app.name') ?? 'WIZ Beauty Salon'),
));

$app->singleton(Uploader::class, static fn (App $a): Uploader => new Uploader(
    (string) ($a->config('app.upload_path') ?? $a->basePath('storage/uploads')),
    (string) ($a->config('app.upload_url') ?? '/uploads'),
    (int) ($a->config('app.upload_max_bytes') ?? 5_242_880),
));

// ----------------------------------------------------------- middleware
$app->singleton(AuthMiddleware::class, static fn (App $a): AuthMiddleware => new AuthMiddleware(
    $a->get(Auth::class),
));

$app->singleton(GuestMiddleware::class, static fn (App $a): GuestMiddleware => new GuestMiddleware(
    $a->get(Auth::class),
));

$app->singleton(CsrfMiddleware::class, static fn (App $a): CsrfMiddleware => new CsrfMiddleware(
    $a->get(Csrf::class),
));

$app->singleton(AdminOnlyMiddleware::class, static fn (App $a): AdminOnlyMiddleware => new AdminOnlyMiddleware(
    $a->get(Auth::class),
));

// ---------------------------------------------------------- controllers
$app->singleton(AuthController::class, static fn (App $a): AuthController => new AuthController(
    $a->get(View::class),
    $a->get(Session::class),
    $a->get(Csrf::class),
    $a->get(AuthService::class),
));

$app->singleton(DashboardController::class, static fn (App $a): DashboardController => new DashboardController(
    $a->get(View::class),
    $a->get(Auth::class),
    $a->get(BookingRepository::class),
    $a->get(MessageRepository::class),
    $a->get(ReviewRepository::class),
    $a->get(CategoryRepository::class),
    $a->get(ActivityLogRepository::class),
    $a->get(UserRepository::class),
    $a->get(Logger::class),
));

$app->singleton(CategoryController::class, static fn (App $a): CategoryController => new CategoryController(
    $a->get(View::class),
    $a->get(CategoryRepository::class),
    $a->get(Uploader::class),
    $a->get(MediaUsage::class),
    $a->get(Session::class),
));

$app->singleton(TermsController::class, static fn (App $a): TermsController => new TermsController(
    $a->get(View::class),
    $a->get(TermsRepository::class),
    $a->get(TermsService::class),
    $a->get(ChangePolicy::class),
    $a->get(ActivityLogRepository::class),
    $a->get(Auth::class),
    $a->get(Session::class),
));

$app->singleton(GalleryController::class, static fn (App $a): GalleryController => new GalleryController(
    $a->get(View::class),
    $a->get(GalleryRepository::class),
    $a->get(CategoryRepository::class),
    $a->get(Uploader::class),
    $a->get(MediaUsage::class),
    $a->get(Session::class),
));

$app->singleton(WebsiteImagesController::class, static fn (App $a): WebsiteImagesController => new WebsiteImagesController(
    $a->get(View::class),
    $a->get(SiteImageRepository::class),
    $a->get(Uploader::class),
    $a->get(MediaUsage::class),
    $a->get(Session::class),
));

$app->singleton(SiteImageRepository::class, static fn (App $a): SiteImageRepository => new SiteImageRepository(
    $a->get(Database::class),
));

$app->singleton(MediaUsage::class, static fn (App $a): MediaUsage => new MediaUsage(
    $a->get(Database::class),
));

$app->singleton(BookingController::class, static fn (App $a): BookingController => new BookingController(
    $a->get(View::class),
    $a->get(BookingRepository::class),
    $a->get(ServiceRepository::class),
    $a->get(CategoryRepository::class),
    $a->get(AvailabilityService::class),
    $a->get(BookingService::class),
    $a->get(Session::class),
    $a->get(Auth::class),
    $a->get(ChangePolicy::class),
));

$app->singleton(ScheduleController::class, static fn (App $a): ScheduleController => new ScheduleController(
    $a->get(View::class),
    $a->get(BusinessHoursRepository::class),
    $a->get(ClosureRepository::class),
    $a->get(AvailabilityService::class),
    $a->get(Session::class),
));

$app->singleton(BookingApiController::class, static fn (App $a): BookingApiController => new BookingApiController(
    $a->get(AvailabilityService::class),
    $a->get(BookingService::class),
    $a->get(ServiceRepository::class),
    $a->get(CategoryRepository::class),
    $a->get(RateLimitRepository::class),
    $a->get(BookingRepository::class),
    (string) ($a->config('app.url') ?? ''),
    (string) ($a->config('app.upload_url') ?? '/uploads'),
    $a->get(SignedLink::class),
));

$app->singleton(PublicBookingController::class, static fn (App $a): PublicBookingController => new PublicBookingController(
    $a->get(View::class),
    $a->get(BookingRepository::class),
    $a->get(BookingService::class),
    $a->get(AvailabilityService::class),
    $a->get(SignedLink::class),
    $a->get(Session::class),
    $a->get(ChangePolicy::class),
));

$app->singleton(MessageController::class, static fn (App $a): MessageController => new MessageController(
    $a->get(View::class),
    $a->get(MessageRepository::class),
    $a->get(Session::class),
));

$app->singleton(ContentApiController::class, static fn (App $a): ContentApiController => new ContentApiController(
    $a->get(GalleryRepository::class),
    $a->get(ReviewRepository::class),
    $a->get(SiteImageRepository::class),
    $a->get(SalonDetails::class),
    $a->get(BusinessHoursRepository::class),
    $a->get(ClosureRepository::class),
    $a->get(ChangePolicy::class),
    $a->get(TermsService::class),
    (string) ($a->config('app.upload_url') ?? '/uploads'),
));

$app->singleton(MessageApiController::class, static fn (App $a): MessageApiController => new MessageApiController(
    $a->get(MessageRepository::class),
    $a->get(RateLimitRepository::class),
    $a->get(MailTransport::class),
    $a->get(EmailTemplateService::class),
    (string) ($a->config('app.url') ?? ''),
    $a->get(MailSettings::class)->salonEmail(),
));

$app->singleton(TeamService::class, static fn (App $a): TeamService => new TeamService(
    $a->get(UserRepository::class),
    $a->get(ActivityLogRepository::class),
));

$app->singleton(UserController::class, static fn (App $a): UserController => new UserController(
    $a->get(View::class),
    $a->get(UserRepository::class),
    $a->get(TeamService::class),
    $a->get(Auth::class),
    $a->get(Session::class),
));

$app->singleton(AccountController::class, static fn (App $a): AccountController => new AccountController(
    $a->get(View::class),
    $a->get(Auth::class),
    $a->get(UserRepository::class),
    $a->get(AuthService::class),
    $a->get(ActivityLogRepository::class),
    $a->get(Session::class),
    $a->get(MailTransport::class),
    $a->get(EmailTemplateService::class),
));

$app->singleton(ActivityController::class, static fn (App $a): ActivityController => new ActivityController(
    $a->get(View::class),
    $a->get(ActivityLogRepository::class),
));

$app->singleton(SettingsController::class, static fn (App $a): SettingsController => new SettingsController(
    $a->get(View::class),
    $a->get(MailSettings::class),
    $a->get(SalonDetails::class),
    $a->get(MailTransport::class),
    $a->get(RateLimitRepository::class),
    $a->get(ActivityLogRepository::class),
    $a->get(Auth::class),
    $a->get(Session::class),
    $a->get(SettingsRepository::class),
    $a->get(AvailabilityService::class),
    $a->get(ChangePolicy::class),
));

$app->singleton(EmailTemplateController::class, static fn (App $a): EmailTemplateController => new EmailTemplateController(
    $a->get(View::class),
    $a->get(EmailTemplateService::class),
    $a->get(ActivityLogRepository::class),
    $a->get(Auth::class),
    $a->get(Session::class),
));

$app->singleton(ReviewController::class, static fn (App $a): ReviewController => new ReviewController(
    $a->get(View::class),
    $a->get(ReviewRepository::class),
    $a->get(ServiceRepository::class),
    $a->get(Session::class),
));

return $app;
