# Tapoda current page and endpoint inventory

Static baseline: branch `uat-20260526`, repository SHA `3d2c3a4b843f73cf0b19c5cb9a4d2e54e80d78aa`.

Primary sources:

- `routes/web.php`
- `routes/api.php`
- `app/Providers/RouteServiceProvider.php`
- `app/Http/Kernel.php`
- `app/Http/Controllers/`
- `app/Business/`
- `resources/views/`

No PHP runtime or deployed database was available. Controller, model, table, view, and side-effect mappings are static-code findings. DB-driven question definitions and deployed schema differences remain uncertain.

The canonical row-level endpoint ledger is [`current-page-inventory.csv`](current-page-inventory.csv).

## 1. Completeness reconciliation

| Item | Count | Reconciliation |
|---|---:|---|
| Concrete HTTP endpoint declarations | 109 | 99 in `routes/web.php`; 10 in `routes/api.php` |
| GET declarations | 74 | 68 web; 6 API |
| POST declarations | 35 | 31 web; 4 API |
| Total `Route::` references | 120 | Includes groups, prefixes, middleware wrappers, and one commented declaration |
| Controller files | 22 | Includes unrouted `TestController` and `NewFlow/ApplyWizardController` |
| Blade files | 91 | Every file classified in section 6 |
| Non-Blade view-directory files | 1 | `resources/views/apply/v2/README.md` |
| Direct route/page Blade views | 48 | Includes placeholder/debug/local-preview pages |
| Layouts | 4 | Public/message and backend/sidebar/no-sidebar |
| Active reusable partials/components | 14 | Public, backend, guided-flow, course, and shared components |
| Active report/print helpers | 6 | Tables, modal fragment, and teacher print |
| Active email templates | 13 | Welcome/reset/invite/accept/confirm/cancel/request-confirm |
| Orphan/unknown Blade files | 6 | Five unreferenced files plus one view behind an unrouted controller action |
| Missing views referenced by code | 6 | All under nonexistent `resources/views/apply-new/` |

All 109 route declarations have one CSV row. All 92 files beneath `resources/views` have one classification below.

## 2. Global routing and middleware

- Web routes are wrapped in `web` by `RouteServiceProvider.php:34-38`. `routes/web.php:240-547` adds a redundant nested `web` group.
- API routes receive `/api` and `api` middleware at `RouteServiceProvider.php:29-33`. `api` applies `throttle:api` at 60 requests/minute by authenticated user or IP.
- `VerifyCsrfToken.php:15-17` exempts `*`; every web POST currently bypasses CSRF enforcement despite carrying the `web` group.
- `backend.auth` requires Laravel authentication and `role === admin`: `BackendAuth.php:28-40`.
- `checkin.access` allows an authenticated administrator or an external session whose `checkin_course_code` exactly matches route/input course: `CheckinAccess.php:12-39`.
- V2 routes use `guided.flow`; production also uses `auth`, but local omits `auth`: `routes/web.php:370`. `EnsureGuidedFlowEnabled.php:12-23` redirects to legacy when V2 is disabled.
- All public and private pages also receive the global CORS middleware.

## 3. Route declaration ledger

This section lists every declaration. Full reads, writes, transitions, related views, and effects are in the CSV.

### 3.1 Public, debug, content, and account

| Source | Method and URI | Handler | Output |
|---|---|---|---|
| `web.php:44` | `GET /welcome` | closure | `welcome` stock Laravel page |
| `web.php:48` | `GET /` | `CourseController@index` | `course.list` |
| `web.php:49` | `GET /course` | `CourseController@index` | Duplicate catalog route |
| `web.php:51` | `GET /test` | closure | Debug JSON containing message callback URL |
| `web.php:90` | `GET /xxxxx/{uid}` | closure | Sends hardcoded cancellation mail; JSON |
| `web.php:103` | `GET /suggest` | closure | `suggest` |
| `web.php:110` | `GET /applicant-qualifications` | closure | `qualification` |
| `web.php:117` | `GET /about` | closure | `about` |
| `web.php:124` | `GET /mail` | closure | Public sample `mail.welcome` with plaintext sample credentials |
| `web.php:132` | `GET /_local/mail-preview/{type?}` | closure | Local-only preview index or selected email template |
| `web.php:243` | `GET /signup` | `CustomAuthController@signup` | Desktop `auth.signup` or mobile `auth.mobile-signup`; route name `login` |
| `web.php:244` | `GET /signout` | `CustomAuthController@signout` | Flush session; redirect |
| `web.php:246` | `POST /signin` | closure | Applicant login JSON |
| `web.php:285` | `GET /agreement` | `CustomAuthController@agreement` | `auth.agreement` |
| `web.php:286` | `GET /forgot` | `CustomAuthController@forgot` | `auth.forgot` |
| `web.php:287` | `GET /messages/{message}` | `MessageController@index` | `message.message` |
| `web.php:288` | `GET /message-accept-success` | `MessageController@accept_success` | `message.accept-success` |
| `web.php:290` | `POST /forgot-password` | closure | Overwrite password, email reset template, JSON |
| `web.php:326` | `GET /select/amphoes` | closure | Amphoe JSON |
| `web.php:340` | `GET /select/tambons` | closure | Tambon/postcode JSON |

Evidence: `routes/web.php:44-354`.

### 3.2 Course discovery, public links, and guided V2

| Source | Method and URI | Handler | Output/effect |
|---|---|---|---|
| `web.php:355` | `GET /course/apply/{apply_token}` | closure | Redirect to V2 profile or legacy training detail |
| `web.php:362` | `GET /course/detail/{course_code}` | `CourseController@detail` | `course.detail` |
| `web.php:363` | `GET /course/confirm/{apply_token}` | `CourseController@confirm` | `approved → confirmed`; email; message redirect |
| `web.php:365` | `GET /course/cancel/{apply_token}` | `ApplyController@cancel` | Cancel/leave mutation; admin email; JSON |
| `web.php:366` | `GET /course/canceled/{apply_token}` | `ApplyController@canceled` | Same mutation; message redirect |
| `web.php:367` | `GET /apply/canceled/{apply_token}` | `ApplyController@canceled` | Alias of previous route |
| `web.php:371` | `GET /course/v2/apply/{apply_token}` | `ApplyController@profileV2` | `apply.v2.profile`; route name `apply.v2.profile` |
| `web.php:372` | `GET /course/v2/apply/{apply_token}/profile` | same | Same view and duplicate route name |
| `web.php:373` | `POST /course/v2/apply/{apply_token}/profile` | `profileV2Store` | Profile/category/manager save |
| `web.php:375` | `GET .../training-history` | `trainingHistoryV2` | `apply.v2.training-history` |
| `web.php:376` | `POST .../training-history` | `trainingHistoryV2Store` | History/attendance save; draft may become applying |
| `web.php:378` | `GET .../preferences` | `preferencesV2` | `apply.v2.preferences` |
| `web.php:379` | `POST .../preferences` | `preferencesV2Store` | Group 11/12 answer upsert |
| `web.php:381` | `GET .../teacher-details` | `teacherDetailsV2` | `apply.v2.teacher-details` |
| `web.php:382` | `POST .../teacher-details` | `teacherDetailsV2Store` | Group 14 answer upsert |
| `web.php:384` | `GET .../commitment` | `commitmentV2` | `apply.v2.commitment` |
| `web.php:385` | `POST .../commitment` | `commitmentV2Store` | Group 9 answers and emergency/representative data |
| `web.php:387` | `GET .../management-details` | `managementDetailsV2` | `apply.v2.management-details` |
| `web.php:388` | `POST .../management-details` | `managementDetailsV2Store` | Group 13/travel/facility save; invited may become confirmed; email |
| `web.php:390` | `GET .../pdpa` | `pdpaV2` | `apply.v2.pdpa` |
| `web.php:391` | `POST .../pdpa` | `pdpaV2Store` | Draft/applying/pending → applied |

All V2 GET handlers call `GuidedFlowService::resolveOrRedirect`; a valid temporary course/type token can cause a GET to insert an `apply_course` draft with step `user_info`: `GuidedFlowService.php:43-165`.

### 3.3 Authenticated member and legacy application

| Source | Method and URI | Handler | Output/effect |
|---|---|---|---|
| `web.php:396` | `GET /member/info/{action?}` | `MemberController@index` | `member.index` tabs |
| `web.php:397` | `POST /member/update-profile` | `MemberController@update` | Update users/contact |
| `web.php:398` | `POST /member/change-password` | closure | Update users password; JSON echoes request |
| `web.php:431` | `GET /course/apply-training-detail/{apply_token}` | `ApplyController@training_detail` | `apply.course-history` |
| `web.php:433` | `POST /course/save-apply` | `SaveController@apply_user_detail` | Save legacy profile/application |
| `web.php:434` | `POST /course/save-apply-course-history` | `SaveController@apply_course_history` | Save history; draft → applying |
| `web.php:435` | `GET /course/apply-question/{apply_token}` | `ApplyController@question` | `apply.question` |
| `web.php:436` | `POST /course/save-apply-question` | `SaveController@apply_question` | Save answers and client-supplied step/status |
| `web.php:437` | `GET /course/apply-agreement/{apply_token}` | `ApplyController@agreement` | `apply.agreement` |
| `web.php:438` | `POST /course/save-apply-agreement` | `SaveController@apply_agreement` | Status → applied |

The complete group uses `auth`, but save actions accept client-supplied application identifiers and state fields. Ownership enforcement is inconsistent between display controllers and save controllers.

### 3.4 Authenticated legacy invitation acceptance

| Source | Method and URI | Handler | Output/effect |
|---|---|---|---|
| `web.php:442` | `GET /accept/user-detail/{apply_token}` | `AcceptController@user_detail` | `accept.user-detail` |
| `web.php:443` | `GET /accept/training-detail/{apply_token}` | `AcceptController@training_detail` | `accept.course-history` |
| `web.php:444` | `GET /accept/question/{apply_token}` | `AcceptController@question` | `accept.question` |
| `web.php:445` | `GET /accept/more-info/{apply_token}` | `AcceptController@more_information` | `accept.more-info` |
| `web.php:446` | `GET /accept/consent-form/{apply_token}` | `AcceptController@consent_form` | `accept.consent-form` |
| `web.php:447` | `GET /accept/done` | nonexistent `AcceptController@done` | Route/action gap |
| `web.php:448` | `POST /accept/save-accept` | `SaveAcceptController@user_detail` | Profile/application/manager save |
| `web.php:449` | `POST /accept/save-course-history` | `SaveAcceptController@course_history` | History/application save |
| `web.php:450` | `POST /accept/save-question` | `SaveAcceptController@question` | Answer and step/status save |
| `web.php:451` | `POST /accept/save-more-info` | `SaveAcceptController@user_more_info` | More-info answers/invite record |
| `web.php:452` | `POST /accept/save-consent-form` | `SaveAcceptController@course_consent` | Applicant decline → rejected; accept → confirmed; confirmation email |

Evidence: `routes/web.php:441-453`, `SaveAcceptController.php:30-629`.

### 3.5 Administrator routes

All routes in this table use `backend.auth`.

| Source | Method and URI | Handler | Output/effect |
|---|---|---|---|
| `web.php:459` | `GET /backend/` | `AdminController@index` | Placeholder `backend.blank` |
| `web.php:460` | `GET /backend/approve` | `BackendApproveController@index` | `backend.approve.index` |
| `web.php:461` | `GET /backend/approve-course/{course_code}/{apply_type?}` | `BackendApproveController@course` | Trainee `backend.approve.course` or staff `course-staff` |
| `web.php:462` | `GET /backend/approve-applicant/{course_code}/{uid}/{apply_id}/{approve_type}` | `BackendApproveController@applicant` | `backend.approve.applicant` |
| `web.php:464` | `POST /backend/approve/save` | `BackendApproveController@store` | Approved action writes invited; reject/cancel; invite record and mail |
| `web.php:465` | `GET /backend/course` | `BackendCourseController@index` | `backend.course.index` |
| `web.php:466` | `GET /backend/course/manage/{course_code}` | `BackendCourseController@manage` | `backend.course.manage` |
| `web.php:467` | `POST /backend/course/update-apply-status` | `BackendCourseController@update_course_apply_status` | Course male/female registration flags |
| `web.php:468` | `GET /backend/course/laundry/{course_code}/{gender?}` | `BackendCourseController@laundry` | `backend.course.laundry` |
| `web.php:469` | `GET /backend/export/laundry/{course_code}/{gender?}` | `BackendExportController@laundry` | XLSX stream |
| `web.php:470` | `POST /backend/update-apply-status` | `BackendCourseController@update_apply_status` | Bulk arbitrary application status; finalize marks alumni |
| `web.php:471` | `POST /backend/request-confirm` | `BackendCourseController@request_confirm` | Confirmation-request email |
| `web.php:472` | `POST /backend/course/update-checkin-password` | `BackendCourseController@update_checkin_password` | Hash/set/clear course password |
| `web.php:474` | `GET /backend/user` | `BackendUserController@index` | `backend.user.index` |
| `web.php:475` | `GET /backend/user/add` | `BackendUserController@formAdd` | `backend.user.form` |
| `web.php:476` | `POST /backend/user/add` | `BackendUserController@add` | Insert backend user |
| `web.php:477` | `GET /backend/user/edit/{id}` | `BackendUserController@detail` | `backend.user.form` |
| `web.php:478` | `POST /backend/user/edit` | `BackendUserController@edit` | Update backend user/password |
| `web.php:479` | `GET /backend/user/delete/{id}` | `BackendUserController@delete` | Soft-delete mutation over GET |
| `web.php:481` | `GET /backend/parole` | `BackendUserParoleController@index` | Miswired `backend.parole.index` over admin users |
| `web.php:482` | `GET /backend/parole/detail/{id}` | `BackendUserParoleController@detail` | `backend.parole.detail`; exposes admin record |
| `web.php:484` | `GET /backend/checkin-course` | `BackendCheckinController@index` | `backend.checkin.course` |
| `web.php:486` | `GET /backend/summary` | `BackendSummaryController@index` | Placeholder `backend.summary.index` |
| `web.php:487` | `GET /backend/report` | `BackendReportController@index` | Applicant report `backend.report.apply-course` |
| `web.php:488` | `GET /backend/export` | `BackendExportController@index` | Eight-sheet applicant XLSX stream |
| `web.php:489` | `GET /backend/report/teacher/{course_code?}` | `BackendReportController@teacher` | `backend.report.teacher` |
| `web.php:490` | `GET /backend/report/teacher/application/{apply_id}` | `BackendReportController@teacherApplication` | JSON with rendered modal partial |
| `web.php:491` | `POST /backend/report/teacher/print` | `BackendReportController@teacherPrint` | `backend.report.teacher-print` |
| `web.php:492` | `GET /backend/logout` | `AdminController@logout` | Auth/session logout over GET |

### 3.6 Backend login and check-in routes

| Source | Method and URI | Middleware/actor | Handler | Output/effect |
|---|---|---|---|---|
| `web.php:497` | `GET /backend/login` | public | `AdminController@login` | `backend.auth.login` |
| `web.php:498` | `GET /backend/checkin-staff-login` | public | `ExternalCheckinAuthController@showLogin` | `backend.auth.checkin-staff-login` |
| `web.php:499` | `POST /backend/checkin-staff-login` | public; `throttle:5,1` | `ExternalCheckinAuthController@login` | Course-password session |
| `web.php:500` | `GET /backend/checkin-staff-logout` | external operator | `ExternalCheckinAuthController@logout` | Clear scoped session |
| `web.php:502` | `GET /backend/checkin-form/{course_code}` | `checkin.access` | `BackendCheckinController@form` | `backend.checkin.form` |
| `web.php:503` | `POST /backend/checkin-save` | `checkin.access` | `BackendCheckinController@store` | Insert checkin; confirmed → checkin |
| `web.php:505` | `POST /backend/signin` | public | closure | Admin authentication; unconditional redirect |
| `web.php:551` | `GET /preview/accept-reminder/{apply_token?}` | local-only registration | closure | `preview.accept-reminder` |

### 3.7 API routes

All receive `/api`, `api`, bindings, and `throttle:api`.

| Source | Method and URI | Extra middleware | Handler | Output/effect |
|---|---|---|---|---|
| `api.php:20` | `GET /api/test` | none | closure | Literal `true` |
| `api.php:24` | `GET /api/user` | `auth:sanctum` | closure | Authenticated user JSON |
| `api.php:28` | `POST /api/signup/otp/request` | none | `SignupController@requestOtp` | Twilio email OTP request |
| `api.php:29` | `POST /api/signup/otp/verify` | none | `SignupController@verifyOtp` | Verify OTP; cache one-use registration token |
| `api.php:30` | `POST /api/signup` | none | `SignupController@store` | Insert user/contact; login; welcome email |
| `api.php:32` | `GET /api/citizen/ready` | none | closure | Personal-ID availability enumeration |
| `api.php:38` | `GET /api/citizen/exists` | none | closure | Inverse enumeration |
| `api.php:44` | `POST /api/checkins/search/{course_code}/{personal_id}` | none | closure | Public confirmed-applicant PII lookup |
| `api.php:148` | `GET /api/apply-stat/{course_code}` | none | closure | Hardcoded zero counters |
| `api.php:168` | `GET /api/username/exists` | none | closure | Backend username availability enumeration |

## 4. Data and state-effect profiles

### 4.1 Catalog and reference data

- Course list reads `course`, `center`, `course_type`, and `tutor_type` through `CourseServices`, plus filter masters through `CourseFilterServices`: `CourseController.php:428-461`.
- Course detail reads those tables, current `apply_course`, and authenticated user's `contact`; it generates temporary trainee/staff application tokens and public attachment URLs: `CourseController.php:304-420`.
- Geography endpoints read `amphoes` and `tambons`; missing required query parameters leave local response variables undefined: `routes/web.php:325-353`.

### 4.2 Registration and account

- Applicant login and backend login read `users` through `Auth::attempt`, then write session state.
- Forgot password reads/writes `users`, generates a new plaintext password, and renders/sends `mail.reset`: `routes/web.php:290-323`.
- OTP request/verify call Twilio Verify and `VerifiedEmailTokenManager`; successful verification produces a one-use cached token. Signup consumes the token, inserts `users` and `contact`, authenticates, and sends `mail.welcome`: `SignupController.php:30-225`.
- Member page reads `users`, `contact`, `prefixes`, geography, countries, languages/education/tutor masters, `apply_course`, course data, history, and teacher information. Profile update writes `users` and `contact`: `MemberController.php:25-396`.

### 4.3 Guided application

- All V2 pages resolve `Apply` with `user.contact`, `course`, and `detail`; first access can insert a draft `apply_course`.
- Profile additionally reads geography, `education_level`, `trainee_type`; POST writes `users`, `contact`, `apply_course`, and via `ProfileSyncService`, `apply_course_manager`.
- Training history reads/writes `training_history_info`, `apply_course`, `contact`, center and custom-period masters. It advances the stored coarse step and can change `draft → applying`.
- Preferences read question/group/choice/answer tables and upsert `question_apply_course`; submitted question-group ID is accepted from the request.
- Teacher details use hardcoded question IDs layered over group 14 and upsert `question_apply_course`.
- Commitment uses group 9, validates emergency contact and commitments, writes answers and application contact fields.
- Management uses group 13, writes answers/travel/facility fields. If current state is `invited`, it writes `confirmed` and `confirmed_date`, then schedules a confirmation email at application termination: `ApplyController.php:1068-1238`.
- PDPA only validates a checkbox, advances the step, writes `applied_date`, and changes `draft|applying|applicant_pending → applied`: `ApplyController.php:1417-1462`, `GuidedFlowService.php:338-367`.

### 4.4 Legacy application and acceptance

- Legacy display controllers perform some ownership checks; save controllers rely on request-supplied encrypted/plain application IDs and state fields.
- `SaveController` writes `apply_course`, `apply_course_manager`, `users`, `contact`, `training_history_info`, and `question_apply_course`: `SaveController.php:23-394`.
- `SaveAcceptController` writes the same core records plus `invite_accept`; final consent either rejects or confirms and dispatches staff/trainee confirmation email: `SaveAcceptController.php:30-629`.
- Legacy accept decline and administrative rejection both persist legacy `rejected`, distinguished only by invitation metadata.

### 4.5 Administrator selection and course operations

- Approval list/detail reads course/session masters, users/contact, application/manager/history, question/choice answers, related courses, teachers, and custom periods.
- Approval mutation maps requested `approved` to stored `invited`; writes `apply_course`, `invite_accept`, invitation timestamp/type/position, and sends invite mail. Other values can write rejected/canceled/left: `BackendApproveController.php:898-1044`.
- Course bulk mutation directly accepts IDs/status. `finalize` additionally sets `contact.is_alumni` and `is_ask_alumni`: `BackendCourseController.php:250-278`.
- Registration toggle writes `course.men_apply_status` and `women_apply_status`.
- Check-in password writes a hash or null to `course.checkin_password`.
- Request-confirm reads applications/users/contact/course and sends `mail.request-confirm`; it does not mark a sent flag in this controller.

### 4.6 Check-in

- External login reads `course.checkin_password`; supports legacy plaintext comparison and current hash comparison, then writes course-scoped session data: `ExternalCheckinAuthController.php:17-80`.
- Check-in search API reads `apply_course`, `users`, `prefixes`, `contact`, `trainee_type`, `question_apply_course`, `question_choices`, and latest `checkins`. It returns full identifying/contact/health-adjacent operational data without `checkin.access`: `routes/api.php:44-146`.
- Check-in save reads a confirmed `Apply`, inserts `checkins`, and writes `apply_course.status = checkin`: `BackendCheckinController.php:273-325`.

### 4.7 Reports, print, and exports

- Applicant report reads `Apply`, course/session data, check-ins, users/contact, question mappings/answers/choices, training history, and custom periods; view is `backend.report.apply-course`.
- Main export independently reconstructs similar data and streams eight PHPExcel worksheets. Status membership differs from the HTML report.
- Teacher report reads course, application, user/contact, training and helper-derived confirmed/history sets. Detail modal and print add related applications, teachers, check-ins, centers, questions/choices, and custom periods.
- Laundry UI and export read course plus gender-filtered applications. UI includes `invited`; export does not. Room/day/cost columns are blank operational worksheet cells.

## 5. Email, document, and export effects

Active email templates:

- Registration: `mail.welcome`.
- Password replacement: `mail.reset`.
- Invitation: `mail.invite`, `mail.invite-staff`.
- Legacy acceptance confirmation: `mail.accept-d03`, `mail.accept-d10`, `mail.accept-staff`.
- V2/current confirmation: `mail.confirmed-trainee-d03`, `mail.confirmed-trainee-d10`, `mail.confirmed-monastic-d10`, `mail.confirmed-staff-all`.
- Confirmation request: `mail.request-confirm`.
- Cancellation notice: `mail.cancel`.

Email attachments/links include `training-intro.pdf`, `practice.pdf`, `practice-dhamma-worker.pdf`, center/boarding maps, public cancellation/confirmation links, and the content image. Sending is synchronous through `Helper::EmailSend`; helper failure is generally swallowed after state mutation.

Export effects:

- `/backend/export`: eight-sheet PHPExcel applicant workbook.
- `/backend/export/laundry/{course}/{gender}`: one-sheet laundry workbook.
- `/backend/report/teacher/print`: Blade print document, maximum ten selected application IDs.

Course and consent documents are linked by views rather than controller downloads: uploaded course attachment, `new-privacy.pdf`, old privacy/guideline/practice documents.

## 6. View classification

### 6.1 Route pages — 48

| View | Route/controller |
|---|---|
| `about.blade.php` | `/about`, `web.php:117` |
| `qualification.blade.php` | `/applicant-qualifications`, `web.php:110` |
| `suggest.blade.php` | `/suggest`, `web.php:103` |
| `welcome.blade.php` | `/welcome`, `web.php:44` |
| `accept/consent-form.blade.php` | `AcceptController@consent_form` |
| `accept/course-history.blade.php` | `AcceptController@training_detail` |
| `accept/more-info.blade.php` | `AcceptController@more_information` |
| `accept/question.blade.php` | `AcceptController@question` |
| `accept/user-detail.blade.php` | `AcceptController@user_detail` |
| `apply/agreement.blade.php` | `ApplyController@agreement` |
| `apply/course-history.blade.php` | `ApplyController@training_detail` |
| `apply/question.blade.php` | `ApplyController@question` |
| `apply/v2/commitment.blade.php` | V2 commitment GET |
| `apply/v2/management-details.blade.php` | V2 management GET |
| `apply/v2/pdpa.blade.php` | V2 PDPA GET |
| `apply/v2/preferences.blade.php` | V2 preferences GET |
| `apply/v2/profile.blade.php` | Two V2 profile GET routes |
| `apply/v2/teacher-details.blade.php` | V2 teacher-details GET |
| `apply/v2/training-history.blade.php` | V2 training-history GET |
| `auth/agreement.blade.php` | `/agreement` |
| `auth/forgot.blade.php` | `/forgot` |
| `auth/mobile-signup.blade.php` | `/signup` on mobile agent detection |
| `auth/signup.blade.php` | `/signup` desktop |
| `backend/approve/applicant.blade.php` | Admin applicant detail |
| `backend/approve/course-staff.blade.php` | Dynamic admin staff-course view |
| `backend/approve/course.blade.php` | Dynamic admin trainee-course view |
| `backend/approve/index.blade.php` | Admin approval course list |
| `backend/auth/checkin-staff-login.blade.php` | External check-in login |
| `backend/auth/login.blade.php` | Admin login |
| `backend/blank.blade.php` | Admin home placeholder |
| `backend/checkin/course.blade.php` | Admin check-in course list |
| `backend/checkin/form.blade.php` | Scoped check-in form |
| `backend/course/index.blade.php` | Admin course list |
| `backend/course/laundry.blade.php` | Laundry screen |
| `backend/course/manage.blade.php` | Course participant management |
| `backend/parole/detail.blade.php` | Active route; miswired admin-user detail |
| `backend/parole/index.blade.php` | Active route; miswired admin-user list |
| `backend/report/apply-course.blade.php` | Applicant report |
| `backend/report/teacher.blade.php` | Teacher report |
| `backend/summary/index.blade.php` | Summary placeholder |
| `backend/user/form.blade.php` | Admin user create/edit and unrouted parole mutations |
| `backend/user/index.blade.php` | Admin users |
| `course/detail.blade.php` | Public course detail |
| `course/list.blade.php` | `/` and `/course` |
| `member/index.blade.php` | Member tabs |
| `message/accept-success.blade.php` | Static accept-success route |
| `message/message.blade.php` | Token-decoded message route |
| `preview/accept-reminder.blade.php` | Local-only preview route |

### 6.2 Layouts — 4

- `layouts/layout.blade.php`
- `layouts/message.blade.php`
- `backend/layouts/layout.blade.php`
- `backend/layouts/layout-nosidebar.blade.php`

### 6.3 Active partials/components — 14

- `apply/v2/_action_styles.blade.php`
- `apply/v2/_progress.blade.php`
- `apply/v2/partials/status-alert.blade.php`
- `backend/includes/footer.blade.php`
- `backend/includes/navbar.blade.php`
- `backend/includes/sidebar.blade.php`
- `components/accept/update-reminder.blade.php`
- `components/course-history/part-time-warning.blade.php`
- `course/course-info.blade.php`
- `course/course-info-v2.blade.php`
- `includes/banner.blade.php`
- `includes/footer.blade.php`
- `includes/header.blade.php`
- `includes/menu.blade.php`

### 6.4 Active print/report helpers — 6

- `backend/report/table.blade.php`
- `backend/report/partials/new-trainee-table.blade.php`
- `backend/report/partials/old-trainee-table.blade.php`
- `backend/report/partials/staff-table.blade.php`
- `backend/report/teacher-application-modal.blade.php`
- `backend/report/teacher-print.blade.php`

### 6.5 Active email templates — 13

- `mail/accept-d03.blade.php`
- `mail/accept-d10.blade.php`
- `mail/accept-staff.blade.php`
- `mail/cancel.blade.php`
- `mail/confirmed-monastic-d10.blade.php`
- `mail/confirmed-staff-all.blade.php`
- `mail/confirmed-trainee-d03.blade.php`
- `mail/confirmed-trainee-d10.blade.php`
- `mail/invite-staff.blade.php`
- `mail/invite.blade.php`
- `mail/request-confirm.blade.php`
- `mail/reset.blade.php`
- `mail/welcome.blade.php`

### 6.6 Orphan/unknown Blade files — 6

No route, controller view call, mail resolver, layout include, or Blade include was found for these five:

- `home.blade.php`
- `includes/footer-home.blade.php`
- `backend/report/index.blade.php`
- `backend/report/teacher-print-draft.blade.php`
- `mail/success.blade.php`

One additional page is referenced only by an unrouted action:

- `apply/user-detail.blade.php` → `ApplyController@user_detail`; the active legacy entry route points to `training_detail`.

### 6.7 Non-view resource

- `apply/v2/README.md`: implementation/readme documentation, not Blade.

## 7. Route/view gaps and legacy duplication

### Definite gaps

1. `GET /accept/done` targets missing `AcceptController::done`: `routes/web.php:447`.
2. `NewFlow/ApplyWizardController` has six unrouted actions and references six nonexistent views:
   - `apply-new.user-personal-information`
   - `apply-new.course-history-information`
   - `apply-new.user-general-information`
   - `apply-new.information-for-teacher`
   - `apply-new.course-rules`
   - `apply-new.information-for-manage`
3. `CourseController@confirm` writes `approved → confirmed` and then calls undeclared `$this->userServices`; constructor only accepts course services: `CourseController.php:21-25,219-278`. A valid request can partially transition before fatal error.
4. Both V2 profile GET declarations use route name `apply.v2.profile`: `routes/web.php:371-372`.
5. `accept/more-info.blade.php:324` retains an AJAX call to nonexistent `/accept/save-apply-question`; active code later calls `/accept/save-more-info`.
6. `AcceptController` has no `done`; `ApplyController@user_detail`, `CourseController@cancel`, `CourseController@canceled`, `CustomAuthController@signin`, `TestController`, and multiple parole mutation methods are unrouted.

### Duplicates and compatibility aliases

- `/` and `/course` share `CourseController@index`.
- `/course/canceled/{token}` and `/apply/canceled/{token}` share `ApplyController@canceled`.
- `/course/cancel/{token}` and the two “canceled” routes all mutate cancellation state but return different response shapes.
- V2 profile has two URI aliases and a duplicated name.
- Legacy application and acceptance routes remain active alongside V2.
- Web `/test` and API `/api/test` are separate debug routes.
- `/api/citizen/ready` and `/api/citizen/exists` are inverse public lookup endpoints.
- Backend course mutation has two similarly named endpoints:
  - `/backend/course/update-apply-status` changes course gender registration flags.
  - `/backend/update-apply-status` changes application lifecycle statuses.
- Backend request-confirm and console `request:confirm` duplicate confirmation-request email behavior with different selection and flagging rules.

### Placeholder/dead behavior exposed by routes

- `/backend/` renders a blank page.
- `/backend/summary` renders a placeholder.
- `/api/apply-stat/{course_code}` always returns zeros.
- `/backend/parole*` displays backend administrator records, not a parole domain.
- `/mail`, `/test`, `/xxxxx/{uid}`, and `/welcome` are public diagnostic/sample routes.

## 8. Public artifact register

These files bypass Laravel route discovery. `verified-artifact` proves the file exists, not that production users need it.

### 8.1 Root static HTML — 8

| File | Proven repository use | Proposed disposition |
|---|---|---|
| `public/agreement.html` | No active Laravel reference found; agreement prototype | `archive` after route/referrer telemetry |
| `public/apply-for-staff.html` | No active Laravel reference found; staff-application prototype | `archive` after route/referrer telemetry |
| `public/apply-for-trainee.html` | No active Laravel reference found; trainee-application prototype | `archive` after route/referrer telemetry |
| `public/backup-signup.html` | No active Laravel reference found; signup backup/prototype | `archive` after route/referrer telemetry |
| `public/course-detail.html` | No active Laravel reference found; course-detail prototype | `archive` after route/referrer telemetry |
| `public/course.html` | No active Laravel reference found; course-list prototype | `archive` after route/referrer telemetry |
| `public/index-xxx.html` | No active Laravel reference found; home/index prototype | `archive` after route/referrer telemetry |
| `public/signup.html` | No active Laravel reference found; signup prototype | `archive` after route/referrer telemetry |

### 8.2 Root PHP — 2

| File | Proven repository use | Proposed disposition |
|---|---|---|
| `public/index.php` | Active Laravel HTTP front controller | `migrate` as framework/runtime scaffold, not a product page |
| `public/home.php` | Public `phpinfo()` diagnostic artifact | `remove` from deployed public root after production path check; never reproduce |

### 8.3 Public PDFs — 12

| File | Proven repository use | Proposed disposition |
|---|---|---|
| `public/apply-form.pdf` | No active code reference found | `archive` pending owner, traffic, retention, and checksum review |
| `public/applyform-for-board.pdf` | No active code reference found | `archive` pending owner, traffic, retention, and checksum review |
| `public/applyform-for-long.pdf` | No active code reference found | `archive` pending owner, traffic, retention, and checksum review |
| `public/guideline-registration-2025.pdf` | Linked by desktop and mobile signup views | `migrate` to versioned Documents & Consent record |
| `public/guideline-registration.pdf` | No active code reference found; older guideline | `archive` pending legal/retention and traffic review |
| `public/manual.pdf` | No active code reference found | `archive` pending owner and traffic review |
| `public/new-privacy.pdf` | Linked by legacy and V2 application consent pages | `migrate` to immutable consent document version |
| `public/practice-dhamma-worker.pdf` | Linked/attached by staff notification variants | `migrate` with notification audience/attachment parity |
| `public/practice.pdf` | Linked/attached by trainee notification variants | `migrate` with notification audience/attachment parity |
| `public/privacy.pdf` | Linked by registration agreement | `migrate` or retain as historical consent version after legal mapping |
| `public/training-intro.pdf` | Linked/attached by route, command, controller, and notification behavior | `migrate` with URL and attachment compatibility |
| `public/uploads/course/course-attachment.pdf` | Uploaded-course artifact; no literal code reference proves database use | `migrate` only if production document mapping/traffic proves ownership; otherwise archive |

Every artifact needs a production URL/referrer check, owner, classification, checksum, retention rule, redirect decision, and rollback path before `archive` or `remove`.

## 9. Security-relevant endpoint observations

- Global CSRF exemption affects all 31 web POST endpoints; the four API POST endpoints use the API group and do not use CSRF.
- Public check-in lookup returns applicant PII with API CORS configuration permitting broad access.
- State-changing GET endpoints include signout/logout, cancellation/confirmation, admin-user delete, and check-in logout.
- `/messages/{message}` base64-decodes attacker-controlled JSON; its view renders title/message and callback without a signed trust boundary.
- Forgot password immediately overwrites the password and emails plaintext credentials.
- Applicant/backend login lack dedicated throttle; only external check-in login has `throttle:5,1`.
- Legacy save endpoints do not consistently bind submitted application IDs to authenticated ownership.
- Admin bulk status updates accept arbitrary IDs and status strings without course scoping or a server whitelist.
- Synchronous email failure can occur after state transition and is commonly swallowed.

## 10. Uncertainties

- The repository migrations do not recreate production tables; table and column existence is inferred from code and manual SQL.
- Actual question/group/choice mappings are DB-driven and may differ from checked-in scripts.
- `view($variable)` resolution was manually traced for approval course/staff and teacher report.
- Mail template resolution was traced through `EmailServices`; provider/runtime delivery was not executed.
- Route runtime order/name resolution was not verified with `php artisan route:list` because PHP is unavailable.
- Blade rendering, JavaScript reachability, downloads, Excel binary correctness, smart-card hardware, and SMTP/Twilio calls were not executed.
