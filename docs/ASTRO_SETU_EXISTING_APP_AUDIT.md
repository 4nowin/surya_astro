# Astro Setu — Comprehensive Existing Application Discovery & Product Audit Report

**Date of Audit:** November 1, 2026  
**Subject:** Existing Codebase, Architecture, API, Database, and Feature Audit for Astro Setu  
**Project Locations:**
- **Flutter Mobile Application:** `C:\Users\rsrav\StudioProjects\astro_setu`
- **Laravel Backend Application:** `C:\wamp64\www\astro_setu`

---

## 1. Executive Summary

Astro Setu is an astrology, spiritual devotion, and religious consultation mobile application powered by a **Flutter** frontend (iOS and Android) and a **Laravel** backend REST API with MySQL, integrated with **Firebase Cloud Firestore** for real-time chat, **Firebase Cloud Messaging (FCM v1)** for push notifications, and **Razorpay** for Indian digital payments.

### Core Discoveries
1. **Nature of the Platform:** Contrary to standard automated algorithmic astrology platforms (which compute planetary ephemeris, birth charts, or Kundli calculations via mathematical engines), the existing Astro Setu application is primarily a **content-management, astrologer marketplace, and spiritual services e-commerce platform**.
2. **Current Implemented Astrology Features:** The only functioning astrology feature is **Horoscope Predictions** (Daily, Tomorrow, Weekly, Monthly, Yearly) broken down into life areas (Love, Career, Health, Money, Travel) and metadata (Lucky Number, Lucky Color, Lucky Time, Mood). All horoscope content is manually composed and uploaded by administrators via the web admin panel.
3. **No Kundli / Vedic Calculation Engine:** There are **zero** computational astrology algorithms or third-party Vedic calculation APIs (e.g., Swiss Ephemeris, VedicAstroAPI, Prokerala, Astrotalk SDKs) integrated in either the Flutter app or the Laravel backend. Features such as Kundli charts, Kundli Matching (Gun Milan), Dosha analysis (Mangal/Kalsarpa), Dasha timelines, Panchang, Numerology, and Tarot **do not exist**.
4. **Active Revenue Streams:**
   - **Astrologer Live Chat Consultation:** Pay-per-minute real-time chat powered by Firebase Firestore with backend-authenticated session initialization and per-minute fee deduction from an in-app wallet.
   - **Pooja Booking:** E-commerce catalog of religious rituals and pujas booked via Razorpay.
   - **Spiritual Donations:** Charitable giving (Temple Maintenance, Cow Donation, Food, Education) processed via Razorpay.
   - **In-App Wallet Recharge:** Pre-funded virtual wallet used to pay for chat consultations.
   - **Premium Membership:** ₹51 subscription (via Razorpay or Wallet balance) that unlocks 30-day access to extended horoscopes (Tomorrow, Weekly, Monthly, Yearly).
5. **Architectural Heritage & Rebranding Artifacts:** The Laravel application originates from an earlier event/retreat booking management platform (containing legacy models like `Guru`, `Promoter`, `Location`, `Cancellation`, `Inquiry`), which was rebranded from **"Navgarah"** (`com.navgarah.app` / `navgarah.com`) to **"Astro Setu"**. Several links, assets, and share texts still hardcode Navgarah domains and identifiers.
6. **Key Technical Deficits:**
   - The user registration screen (`sign_up.dart`) is an empty stub; new users can only sign up via Google OAuth.
   - Review submission endpoints and UI do not exist (reviews can only be seeded or manually inserted).
   - Incomplete contracts between Flutter and Laravel (e.g., `chart_preference` in Flutter has no corresponding DB column in `users`).
   - Call consultation buttons are commented out; no WebRTC or audio/video calling SDK is integrated.

---

## 2. Flutter Application Overview

### 2.1 Technical Specs
- **App Name:** Astro Setu (Package identifier / app name in `pubspec.yaml`: `astro`, version `1.0.0+11`)
- **SDK Target:** Dart SDK `^3.5.4`, Flutter 3.x
- **State Management:** **GetX** (`^4.7.2`) utilizing `GetxController`, `Obx`, and reactive variables (`RxString`, `RxInt`, `RxDouble`, `RxList`). While `provider: ^6.1.2` is in `pubspec.yaml`, all controllers and navigation rely strictly on GetX.
- **Local Storage:** `shared_preferences` (`^2.5.3`) wrapped in a reactive `StorageService` (`lib/services/storage.dart`) registered as a singleton with `Get.putAsync`.
- **Internationalization:** English (`en`) and Hindi (`hi`) supported through `AppTranslations` (`lib/lang/translations.dart`) and GetX locale switching.
- **Networking:** `http` (`^1.4.0`) encapsulated in `CallApi` (`lib/data/api/my_api.dart`).
- **Real-time Messaging:** `cloud_firestore` (`^5.6.8`) and `firebase_messaging` (`^15.2.6`).
- **Payments:** `razorpay_flutter` (`1.4.0`).

### 2.2 Complete Screen Inventory (20 Screens)

| # | Screen / Page | File Path | Navigation Route / Entry Point | Purpose & User Capabilities |
|---|---------------|-----------|--------------------------------|-----------------------------|
| 1 | **SplashScreen** | `lib/views/screens/splash/splashscreen.dart` | App startup (`main.dart` home) | Displays animated star background and app logo. Waits 2 seconds; redirects to `AuthPage` if language already selected, else `LanguagePage`. |
| 2 | **LanguagePage** | `lib/views/welcome/language_page.dart` | First launch from `SplashScreen` | Allows user to choose app language: English (`en`) or Hindi (`hi`). Saves selection to local storage and redirects to `AuthPage`. |
| 3 | **AuthPage** | `lib/data/auth/auth_page.dart` | From `SplashScreen` or `LanguagePage` | Authentication gatekeeper. Checks network connectivity, validates bearer token against backend (`/api/auth/check`), refreshes wallet balance, updates FCM token, and subscribes to language-specific notification topics (`horoscope-en`/`horoscope-hi`). Renders offline screen with "Try Again" if no connection, loading screen while validating, `NavBarCurved` if logged in, or `SignIn` if unauthenticated. |
| 4 | **SignIn** | `lib/views/signup_login/sign_in.dart` | Rendered by `AuthPage` when unauthenticated | Allows email/password login or Google One-Tap Sign-In (`google_sign_in`). Validates inputs, authenticates via API, updates FCM token, and navigates to `NavBarCurved`. |
| 5 | **SignUp** | `lib/views/signup_login/sign_up.dart` | Dead route (not linked in navigation) | **Incomplete / Abandoned:** Contains only an empty `Scaffold()` with commented-out registration logic. No registration form exists for email/password users. |
| 6 | **NavBarCurved** | `lib/views/screens/bottomnav/curved_bottom_navbar.dart` | Root shell after successful authentication | Core navigation shell containing a custom painter curved navigation bar with 5 bottom tabs and a central floating action button (FAB) for Donations. Hosts an `IndexedStack` of 6 sub-pages. |
| 7 | **HomeScreen** | `lib/views/screens/home/home_screen.dart` | Tab Index 0 of `NavBarCurved` | Primary dashboard. Shows personalized greeting, App Drawer burger icon, daily horoscope summary card, horizontal scrollable list of online astrologers with instant chat/profile actions, and featured pooja banner carousel. |
| 8 | **PoojaScreen** | `lib/views/screens/pooja/pooja_screen.dart` | Tab Index 1 of `NavBarCurved` | Catalog of religious rituals. Features category filter chips ("All Pooja", "Featured Poojas", and custom tags). Displays pooja cards with thumbnail, title, excerpt, date, and "Book Now" CTA. |
| 9 | **DetailScreen (Pooja)** | `lib/views/screens/pooja/detail_screen.dart` | Tapped from `HomeScreen` carousel or `PoojaScreen` card | Detailed pooja page. Shows ritual banner image, start date, countdown timer to event start, HTML-rendered description, pricing, discount badge, shareable screenshot generator, and "Book Now" button triggering Razorpay payment flow. |
| 10 | **AstrologerScreen** | `lib/views/screens/astrologer/astrologer_screen.dart` | Tab Index 2 of `NavBarCurved` | Astrologer directory. Displays wallet balance header, horizontal filter chips for astrologer expertise (e.g., Vedic, Numerology, Tarot), and cards listing astrologers with experience, languages, per-minute price, and direct "Chat" CTA. |
| 11 | **AstrologerDetailScreen** | `lib/views/screens/astrologer/astrologer_detail_screen.dart` | Tapped from astrologer card on Home or Directory | Complete astrologer profile. Shows avatar, expertise, years of experience, spoken languages, star ratings, review list, HTML description, consultation fees, social sharing card generator, and fixed bottom "Chat" button. (Call button is commented out). |
| 12 | **ChatScreen** | `lib/views/screens/chat/chat_screen.dart` | Tapped from "Chat" button on astrologer cards | 1-on-1 consultation chat interface with themed celestial background. Connects to backend `/chat/start-session` and subscribes to Firebase Firestore collection `chats/{sessionId}/messages`. Sends messages via Firestore, triggers backend FCM notifications to astrologers, runs a 1-minute interval timer deducting per-minute fee from wallet, and automatically terminates chat if balance drops below astrologer's rate. |
| 13 | **DonationScreen** | `lib/views/screens/donation/donation_screen.dart` | Central FAB (Tab Index 3) of `NavBarCurved` | Spiritual charity portal. Explains donation virtues, presents a "Donate Now" button opening a bottom sheet to select purpose (Temple Maintenance, Cow Donation, Education, Food) and enter amount, launching Razorpay order creation. |
| 14 | **HoroscopeScreen** | `lib/views/screens/horoscope/horoscope_screen.dart` | Tab Index 4 of `NavBarCurved` | Zodiac prediction grid. Offers period selector tabs ("Today", "Tomorrow", "Weekly", "Monthly", "Yearly"). Displays 12 zodiac cards with icon, sign name in localized language, truncated excerpt, and "Read More" button. Non-premium users accessing Tomorrow/Weekly/Monthly/Yearly are redirected to `PaywallScreen`. |
| 15 | **HoroscopeDetailScreen** | `lib/views/screens/horoscope/horoscope_detail_screen.dart` | Tapped from any horoscope grid card | Comprehensive zodiac prediction page. Features top horizontal zodiac switcher, period tabs, metadata card (lucky number, lucky color, lucky time, mood), HTML prediction text, specific life category cards (Love, Career, Health, Money, Travel), and a screenshot share button. |
| 16 | **ProfileScreen** | `lib/views/screens/profile/profile_screen.dart` | Tab Index 5 of `NavBarCurved` | User profile overview. Displays user avatar, name, email, wallet balance card with "Add Money" shortcut, astrological birth info cards (Place of Birth, Country, Date of Birth, Birth Time), phone, gender, chart preference, and "Edit Profile" button. |
| 17 | **EditProfileScreen** | `lib/views/screens/profile/edit_profile_screen.dart` | From `ProfileScreen` edit button or triggered before pooja booking if phone is missing | Profile edit form. Allows updating profile photo via gallery (`image_picker`), name, phone, gender dropdown, date of birth picker (`showDatePicker`), place of birth, time of birth picker (`showTimePicker`), and country/location. Uploads multipart form data to `/api/{lang}/update-profile`. |
| 18 | **PaywallScreen** | `lib/views/screens/paywall/paywall_screen.dart` | Triggered when accessing premium horoscope tabs or from Drawer | Membership upgrade screen. Promotes premium astrological features at ₹51. Offers two payment paths: Razorpay gateway checkout or direct deduction from virtual wallet balance. On success, sets user role to `premium`. |
| 19 | **PaymentSuccessPage** | `lib/views/screens/payment/payment_success_page.dart` | Pushed by `RazorpayService` after successful payment verification | Celebration screen with confetti animation (`confetti`), transaction confirmation, receipt summary (payment type, payment for, amount, order ID), and "Back to Home" button. |
| 20 | **ProductScreen** | `lib/views/screens/product/product_screen.dart` | Dead screen (not referenced anywhere in the app) | **Unused Mockup:** Hardcoded UI containing mock categories for gemstones, bracelets, earrings, and yantras with dummy assets and mock addresses. Not connected to any API or navigation route. |

### 2.3 Navigation Architecture
- **Root Routing:** The app uses GetX state-driven root switching in `SplashScreen` and `AuthPage`.
- **Main Shell:** `NavBarCurved` (`curved_bottom_navbar.dart`) maintains state using an `IndexedStack` across 6 views:
  - Index 0: `HomeScreen`
  - Index 1: `PoojaScreen`
  - Index 2: `AstrologerScreen`
  - Index 3: `DonationScreen` (via central elevated FAB)
  - Index 4: `HoroscopeScreen` (gated by user role check: free users are prompted with `PaywallScreen`)
  - Index 5: `ProfileScreen`
- **Slide-out Navigation Drawer (`AppDrawer`):** Accessible via burger icon from Home, Astrologers, Pooja, Horoscope, Donation, and Profile screens. Contains shortcuts to all main tabs, a Dark Mode switch (`ThemeController`), a language switcher bottom sheet, and an authenticated logout dialog.

### 2.4 User Profile & Birth Data Collected
In `EditProfileScreen`, the app collects:
1. Profile Picture (`image_picker` gallery upload)
2. Full Name
3. Phone Number (mandatory before booking poojas)
4. Gender (`male`, `female`, `other`, `prefer_not_to_say`)
5. Date of Birth (`YYYY-MM-DD` via DatePicker)
6. Place of Birth (free-form text input)
7. Time of Birth (`HH:MM` via TimePicker)
8. Country / Location (free-form text input)
9. *Chart Preference:* Observable in `AuthController` (`preferredChartType = 'North Indian'`), displayed in `ProfileScreen`, but missing input controls in `EditProfileScreen` and unpersisted on backend.

### 2.5 Third-Party Flutter Packages & Roles

| Package | Version | Purpose in Astro Setu |
|---------|---------|-----------------------|
| `get` | `^4.7.2` | Primary state management, reactive controllers, navigation (`Get.to`, `Get.offAll`), dialogs, snackbars, and translations. |
| `http` | `^1.4.0` | REST API communication with the Laravel backend (`CallApi`). |
| `provider` | `^6.1.2` | Declared in dependencies but unused; superseded by GetX. |
| `shared_preferences` | `^2.5.3` | Persistent local key-value storage for auth tokens, language, and cached user details. |
| `razorpay_flutter` | `1.4.0` | Native Android/iOS Razorpay checkout integration for all payments. |
| `cloud_firestore` | `^5.6.8` | Real-time NoSQL message synchronization for 1-on-1 astrologer chat. |
| `firebase_core` | `^3.13.1` | Core Firebase SDK initialization (`DefaultFirebaseOptions`). |
| `firebase_auth` | `^5.5.4` | Firebase authentication support. |
| `firebase_messaging` | `^15.2.6` | Remote push notifications and topic subscriptions (`horoscope-en`, etc.). |
| `flutter_local_notifications` | `^19.2.1` | Foreground notification display and notification channels. |
| `google_sign_in` | `^6.3.0` | Native Google OAuth authentication flow. |
| `image_picker` | `^1.1.2` | Selecting profile photo from device gallery. |
| `intl` | `^0.20.2` | Date formatting and parsing for birth dates and pooja countdowns. |
| `flutter_widget_from_html` | `^0.16.0` | Rendering rich HTML descriptions for poojas, horoscopes, and astrologer bios. |
| `carousel_slider` | `^5.0.0` | Horizontal banner slider for featured poojas on HomeScreen. |
| `dots_indicator` | `^4.0.1` | Page indicator dots for carousel sliders. |
| `screenshot` | `^3.0.0` | Capturing widgets as images for social sharing. |
| `share_plus` | `^11.0.0` | Native OS sharing of captured horoscope/pooja screenshots and referral links. |
| `confetti` | `^0.8.0` | Particle confetti animation on successful payment confirmation. |
| `path_provider` | `^2.1.5` | Accessing temporary filesystem storage for shared screenshot files. |
| `connectivity_plus` | `^6.1.4` | Checking device network connection state. |
| `internet_connection_checker` | `^3.0.1` | Verifying actual internet ping reachability. |
| `logger` | `^2.5.0` | Formatted console logging (`LogService`). |
| `permission_handler` | `^12.0.0+1` | Requesting notification and device permissions. |
| `flutter_dotenv` | `^5.2.1` | Environment variable parsing. |
| `device_info_plus` | `^11.4.0` | Device metadata extraction. |

### 2.6 UI/UX Design System
- **Theme Modes:** Light and Dark mode dynamically managed by `ThemeController` (`themeMode` stored in `StorageService`).
- **Color Palette (`lib/constants/appcolors.dart`):**
  - **Primary Gold / Saffron:** `#F7CB45`
  - **Secondary Purple:** `#5E3A99`
  - **Accent Maroon:** `#8B1E3F`
  - **Light Theme Background:** `#FFF8E1` (Soft Cream)
  - **Light Theme Cards:** `#FFF3CD` (Warm Pastel Yellow)
  - **Dark Theme Background:** `#121212` (True Black)
  - **Dark Theme Cards:** `#1E1E1E` (Dark Charcoal)
  - **Chat & Header Theme:** `#4B2E1E` (Deep celestial brown-gold)
- **Typography:** Custom font families defined in `pubspec.yaml`:
  - `bold`: Poppins Bold (`assets/fonts/Poppins-Bold.ttf`)
  - `regular`: Poppins Regular (`assets/fonts/Poppins-Regular.ttf`)
- **Key Reusable Widgets (`lib/views/widgets/`):**
  - `AnimatedStarBackground`: Twinkling night sky star background using custom canvas painting and periodic timers.
  - `BottomNavCurvePainter`: Custom bezier curve canvas painter rendering the notched bottom navigation bar.
  - `AddMoneyBottomSheet`: Modal sheet with amount validation and Razorpay payment trigger for wallet top-ups.
  - `HoroscopeInfoCard` & `DailyHoroscopeCard`: Structured cards displaying lucky metrics and zodiac icons.
  - `AstrologerCard` & `AstrologerHomeCard`: Reusable astrologer profiles with expertise badges and chat action buttons.
  - `FeaturedPoojaSlider`: Auto-scrolling carousel of top poojas.

---

## 3. Laravel Backend Overview

### 3.1 Technical Specs
- **Laravel Framework Version:** `13.38.0` (installed via `laravel/framework: ^13.0` in `composer.lock`)
- **PHP Version Requirement:** `^8.3` (platform configured `8.4.26`)
- **API Authentication:** **Laravel Sanctum** (`^4.0`) via Personal Access Tokens (`Bearer <token>`). Also includes `tymon/jwt-auth: ^2.1` in composer dependencies for JWT capability.
- **Authorization & RBAC:** **Spatie Laravel Permission** (`spatie/laravel-permission: ^6.25`) with roles (`Astrologer`, `Admin`, `Super Admin`) and permission tables.
- **Payment Gateway:** **Razorpay PHP SDK** (`razorpay/razorpay: ^2.9`).
- **Cloud Database & Chat Backend:** **Google Cloud Firestore** (`google/cloud-firestore: ^1.50|^2.0`) & **Kreait Firebase PHP** (`kreait/firebase-php: ^8.0|^7.10`).
- **Push Notifications:** Firebase Cloud Messaging HTTP v1 API (`google/apiclient: ^2.18`, `google/auth: ^1.47`).

### 3.2 Database Schema & Table Inventory (30 Tables)

| # | Table Name | Migration File | Key Columns | Purpose & Role |
|---|------------|----------------|-------------|----------------|
| 1 | `users` | `2014_10_12_000000_create_users_table.php` | `id`, `name`, `email`, `phone`, `password`, `profile_image`, `language`, `wallet_balance`, `gender`, `birth_time`, `date_of_birth`, `place_of_birth`, `country`, `role` (enum: `free`, `premium`, `gold`, `platinum`, `diamond`), `status`, `google_id`, `device_token`, `fcm_token`, `referral_code`, `referred_by`, `premium_started_at` | Primary end-user account table containing birth details, virtual wallet balance, subscription status, and auth tokens. |
| 2 | `password_reset_tokens` | `2014_10_12_100000_...` | `email`, `token`, `created_at` | Password reset tokens for web auth. |
| 3 | `password_resets` | `2014_10_12_100000_...` | `email`, `token`, `created_at` | Legacy password reset tokens. |
| 4 | `failed_jobs` | `2019_08_19_000000_...` | `id`, `uuid`, `connection`, `queue`, `payload`, `exception`, `failed_at` | Laravel queue failure log table. |
| 5 | `personal_access_tokens`| `2019_12_14_000001_...` | `id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at` | Sanctum API tokens issued to users and astrologers. |
| 6 | `permissions` | `2023_12_26_113856_...` | `id`, `name`, `guard_name` | Spatie RBAC permission definitions. |
| 7 | `roles` | `2023_12_26_113856_...` | `id`, `name`, `guard_name` | Spatie RBAC role definitions (`Astrologer`, etc.). |
| 8 | `model_has_permissions`| `2023_12_26_113856_...` | `permission_id`, `model_type`, `model_id` | Direct permission assignments to models. |
| 9 | `model_has_roles` | `2023_12_26_113856_...` | `role_id`, `model_type`, `model_id` | Role assignments to admins and astrologers. |
| 10 | `role_has_permissions` | `2023_12_26_113856_...` | `permission_id`, `role_id` | Permission mappings to roles. |
| 11 | `admins` | `2023_12_27_094709_...` | `id`, `name`, `email`, `password`, `is_online`, `is_typing`, `fcm_token` | Admin users and astrologer authentication accounts. |
| 12 | `inquiries` | `2023_12_27_152410_...` | `id`, `name`, `email`, `mobile`, `message`, `status` | General web inquiries and booking contact forms. |
| 13 | `cancellations` | `2023_12_28_145245_...` | `id`, `policy_name`, `description`, `deduction_percentage` | Legacy cancellation policies. |
| 14 | `gurus` | `2023_12_28_145435_...` | `id`, `name`, `bio`, `image` | Legacy spiritual teacher profiles. |
| 15 | `promoters` | `2023_12_28_151028_...` | `id`, `name`, `code`, `commission` | Affiliate/promoter tracking for orders. |
| 16 | `gallery_images` | `2023_12_28_151044_...` | `id`, `title`, `image_path` | Gallery media assets. |
| 17 | `locations` | `2023_12_28_151102_...` | `id`, `name`, `address`, `city`, `state` | Event/retreat locations. |
| 18 | `coupons` | `2023_12_28_151118_...` | `id`, `code`, `discount_type`, `value`, `valid_until` | Discount coupon codes. |
| 19 | `orders` | `2024_03_02_092753_...` | `id`, `name`, `mobile`, `email`, `inquiry_id`, `payment_id`, `total_price`, `status`, `promoter_id` | Orders created for booked poojas and physical services. |
| 20 | `order_details` | `2024_03_02_092716_...` | `id`, `order_id`, `item_type`, `item_id`, `quantity`, `price` | Line items associated with orders. |
| 21 | `payments` | `2024_03_02_092739_...` | `id`, `user_id`, `rzp_payment_id`, `rzp_order_id`, `payment_method`, `rzp_signature`, `order_id`, `payment_type` (`Pooja`, `Donation`, `Wallet`, `Premium`), `payment_for`, `phone`, `email`, `wallet`, `cancel_reason`, `amount`, `status` (`CREATED`, `PENDING`, `CONFIRMED`, `FAILED`, `CANCELLED`) | Master payment log for all Razorpay transactions. |
| 22 | `poojas` | `2025_02_20_161649_...` | `id`, `admin_id`, `image`, `title`, `language`, `tag`, `excerpt`, `description`, `start_date`, `end_date`, `price`, `original_price`, `active`, `home_priority` | Ritual and puja definitions, event schedules, pricing, and home screen display priority. |
| 23 | `horoscopes` | `2025_03_10_135828_...` | `id`, `image`, `zodiac_sign` (enum of 12 signs), `horoscope_type` (`daily`, `weekly`, `monthly`, `yearly`), `language`, `zodiac`, `tag`, `context`, `love`, `career`, `money`, `health`, `travel`, `mood`, `lucky_number`, `lucky_color`, `lucky_time`, `start_date`, `end_date` | Pre-authored horoscope predictions categorized by zodiac sign, time horizon, and life facet. |
| 24 | `products` | `2025_03_10_135845_...` | `id`, `title`, `image`, `description`, `price`, `original_price`, `language`, `active` | E-commerce products (gemstones, yantras, rudrakshas). |
| 25 | `astrologers` | `2025_05_10_090445_...` | `id`, `admin_id`, `image`, `name`, `language`, `astrologer_language`, `expertise`, `experience`, `excerpt`, `description`, `chat_minutes`, `call_minutes`, `price`, `call_price`, `original_price`, `status` (`online`, `offline`), `active` | Astrologer profiles linked to Admin authentication accounts with consultation pricing and online status. |
| 26 | `reviews` | `2025_05_10_102147_...` | `id`, `astrologer_id`, `user_id`, `rating`, `comment` | Customer reviews and star ratings for astrologers. |
| 27 | `chat_sessions` | `2025_05_19_102154_...` | `id`, `user_id`, `admin_id`, `astrologer_id`, `started_at`, `ended_at`, `total_minutes`, `total_fee` | Consultation session tracking duration and total billed fee. |
| 28 | `chats` | `2025_05_19_102154_...` | `id`, `chat_session_id`, `user_id`, `admin_id`, `astrologer_id`, `message`, `sender` (`user`, `astrologer`), `sent_at` | MySQL archive of messages exchanged in chat sessions. |
| 29 | `chat_payments` | `2025_05_23_083730_...` | `id`, `chat_session_id`, `user_id`, `admin_id`, `astrologer_id`, `amount`, `deducted_at` | Audit trail of per-minute wallet fee deductions during consultations. |
| 30 | `delete_requests` | `2025_06_23_121118_...` | `id`, `name`, `email`, `mobile`, `reason`, `status` | User account deletion requests submitted via web form. |

### 3.3 Complete API Route Inventory (49 Endpoints)

#### Public Content & Notification Endpoints
1. `GET /api/enquiry` — Lists inquiries (`ApiController@inquiry`).
2. `GET /api/{id}/products` — Lists active products for language `$id` (`ApiController@products`).
3. `GET /api/{lang?}/poojas` — Lists active regular poojas (`PoojaController@index`).
4. `GET /api/{lang?}/home-poojas` — Lists top 3 featured poojas (`PoojaController@homePoojas`).
5. `GET /api/{lang?}/horoscopes` — Fetches today, tomorrow, weekly, monthly, and yearly horoscopes (`HoroscopeController@index`).
6. `GET /api/{lang?}/home-horoscopes` — Fetches today's featured horoscope for home screen (`HoroscopeController@homeHoroscope`).
7. `GET /api/{lang?}/astrologers` — Lists all active astrologers with reviews and average rating (`AstrologerController@index`).
8. `GET /api/astrologers/{id}/reviews` — Fetches reviews for a specific astrologer (`ReviewController@index`).
9. `POST /api/{lang?}/horoscope-notification` — Sends broadcast push notification to topic `horoscope-{lang}` (`SendNotificationController@sendHoroscopeNotification`).
10. `POST /api/{lang?}/pooja-notification` — Sends broadcast push to topic `pooja-{lang}` (`SendNotificationController@sendPoojaNotification`).
11. `POST /api/{lang?}/astrologer-notification` — Sends broadcast push to topic `astrologers-{lang}` (`SendNotificationController@sendAstrologerNotification`).
12. `POST /api/{lang?}/donation-notification` — Sends broadcast push to topic `donation-{lang}` (`SendNotificationController@sendDonationNotification`).
13. `GET /api/fcm-token` — Obtains Firebase OAuth2 access token (`NotificationController@getAccessToken`).

#### User Authentication & OAuth Endpoints
14. `POST /api/{lang?}/login` — Authenticates user via email/password and issues Sanctum token (`AuthController@login`).
15. `POST /api/{lang?}/auth/google` — Verifies Google ID token, upserts user, and issues Sanctum token (`AuthController@loginWithGoogle`).
16. `GET /api/auth/google/redirect` — Socialite redirect to Google OAuth consent (`AuthController@redirectToGoogle`).
17. `GET /api/auth/google/callback` — Socialite OAuth callback handler (`AuthController@handleGoogleCallback`).

#### Astrologer Portal Endpoints (`/api/astrologer/...`)
18. `POST /api/astrologer/astro-register` — Registers an astrologer admin account and assigns `Astrologer` role (`AdminAuthController@register`).
19. `POST /api/astrologer/astro-login` — Authenticates astrologer, returns Sanctum token, and generates Firebase custom token (`AdminAuthController@login`).
20. `POST /api/astrologer/save-token` — Saves astrologer admin FCM device token (`NotificationController@saveToken`).
21. `GET /api/astrologer/me` — Returns authenticated astrologer profile (`AdminAuthController@me`, requires `role:Astrologer`).
22. `POST /api/astrologer/logout` — Revokes astrologer token (`AdminAuthController@logout`, requires `role:Astrologer`).
23. `GET /api/astrologer/chat-sessions` — Lists consultation chat sessions for astrologer (`AstrologerChatController@chatSessions`).
24. `GET /api/astrologer/get-status` — Returns astrologer online/offline status (`AstrologerChatController@getStatus`).
25. `POST /api/astrologer/set-online` — Sets astrologer online status (`AstrologerChatController@setOnline`).
26. `POST /api/astrologer/toggle-online` — Toggles astrologer online status (`AstrologerChatController@toggleOnline`).
27. `GET /api/astrologer/chat/astrologer/history` — Returns recent chat history grouped by session (`AstrologerChatController@getAstrologerChats`).
28. `GET /api/astrologer/booked-poojas` — Lists confirmed pooja bookings (`AstrologerController@getBookedPoojas`).
29. `GET /api/astrologer/donations` — Lists confirmed donations (`AstrologerController@getDonations`).
30. `POST /api/send-to-astrologer` — Sends direct push notification to an astrologer's device (`NotificationController@sendToAstrologer`).

#### Authenticated User Endpoints (`auth:sanctum`)
31. `GET /api/user` — Returns authenticated user model (`Request::user()`).
32. `GET /api/{lang?}/auth/check` — Validates auth, auto-downgrades subscriptions older than 30 days (`role = 'free'`), and returns user profile.
33. `GET /api/{lang?}/user-details` — Returns user profile details, wallet balance, and birth info (`UserController@userProfile`).
34. `POST /api/{lang?}/update-profile` — Updates user profile details and processes uploaded avatar (`UserController@updateProfile`).
35. `POST /api/{lang?}/update-user-fcm-token` — Saves user's FCM device push token (`UserController@updateFcmToken`).
36. `POST /api/{lang?}/logout` — Revokes current user access token (`AuthController@logout`).

#### Payments & Razorpay Endpoints (`auth:sanctum`)
37. `POST /api/{lang?}/razorpay/create-pooja-order` — Creates Razorpay order for ritual booking (`PaymentController@createPoojaOrder`).
38. `POST /api/{lang?}/razorpay/create-donation-order` — Creates Razorpay order for charity contribution (`PaymentController@createDonationOrder`).
39. `POST /api/{lang?}/razorpay/add-wallet-money` — Creates Razorpay order for wallet recharge (`PaymentController@addWalletMoney`).
40. `POST /api/{lang?}/razorpay/create-premium-order` — Creates Razorpay order for ₹51 premium membership (`PaymentController@createPremiumUser`).
41. `POST /api/{lang?}/razorpay/verify` — Verifies HMAC SHA-256 signature, confirms payment, credits wallet / activates premium, and sends booking FCM (`PaymentController@verifySignature`).
42. `POST /api/{lang?}/razorpay/payment/save-wallet` — Logs external wallet name if used (`PaymentController@saveWallet`).
43. `POST /api/{lang?}/razorpay/payment/mark-cancelled` — Marks payment record as CANCELLED (`PaymentController@markPaymentCancelled`).
44. `POST /api/{lang?}/razorpay/payment/mark-failed` — Marks payment record as FAILED with error code (`PaymentController@markPaymentFailed`).
45. `POST /api/{lang?}/wallet/pay-for-premium` — Deducts ₹51 directly from wallet balance and activates 30-day premium membership (`PaymentController@payForPremium`).

#### Consultation Chat Endpoints (`auth:sanctum`)
46. `POST /api/{lang?}/chat/start-session` — Initializes chat session, creates Firestore chat doc metadata, and returns session ID (`ChatController@startSession`).
47. `POST /api/{lang?}/chat/deduct-fee` — Deducts 1 minute consultation fee from wallet; throws 402 if balance insufficient (`ChatController@deductFee`).
48. `POST /api/{lang?}/chat/end-session` — Terminates consultation session (`ChatController@endChatSession`).
49. `POST /api/{lang?}/firebase-chat/store` — Archives Firestore chat messages into MySQL database (`FirebaseChatController@store`).

### 3.4 Admin Panel Architecture
The admin web application runs on standard Laravel Blade templates with Session Authentication (`auth:admin` guard):
- **Entry URL:** `/admin/login` (`Admin\AuthController@login`)
- **Dashboard:** `/admin` (`Admin\HomeController@index`)
- **Astrologer Management:** `/admin/astrologer` (`Admin\AstrologerController`) — Full CRUD, pricing setup, languages, expertise, and active toggle.
- **Horoscope Management:** `/admin/horoscope` (`Admin\HoroscopeController`) — Date-based, sign-based, and type-based authoring of horoscope content in English and Hindi.
- **Pooja Management:** `/admin/pooja` (`Admin\PoojaController`) — Ritual creation, descriptions, scheduling, and `home_priority` ordering (1-4).
- **Product Management:** `/admin/product` (`Admin\ProductController`) — E-commerce store management.
- **Push Notification Broadcasts:** `/admin/fcm/send` (`Admin\NotificationController`) — Manual FCM push message sender.
- **File Manager:** `/admin/files` (`Admin\FileManagerController`) — CKEditor and media uploader.
- **RBAC Management:** `/admin/admins` and `/admin/roles` (`Admin\AdminsController`, `Admin\RolesController`) — Admin accounts and role assignment.
- **Legacy Sections:** Coupons, Promoters, Locations, Inquiries, Gurus, Gallery, Cancellation.

---

## 4. Existing Astrology Features Inventory

| Feature | Audit Status | Implementation Details & File References |
|---------|--------------|------------------------------------------|
| **Daily Horoscope** | **IMPLEMENTED** | Admin manually creates predictions per zodiac sign. Fetched via `/api/{lang}/horoscopes` and `/api/{lang}/home-horoscopes`. Rendered on `HomeScreen` (`daily_horoscope.dart`) and `HoroscopeScreen`. Accessible to all users. |
| **Tomorrow Horoscope** | **IMPLEMENTED (Premium)** | Stored in `horoscopes` table (`horoscope_type = 'daily'`). Gated in Flutter UI (`horoscope_screen.dart#L61`): free users are redirected to `PaywallScreen`. |
| **Weekly Horoscope** | **IMPLEMENTED (Premium)** | Stored in `horoscopes` table (`horoscope_type = 'weekly'`). Gated behind ₹51 premium membership. |
| **Monthly Horoscope** | **IMPLEMENTED (Premium)** | Stored in `horoscopes` table (`horoscope_type = 'monthly'`). Gated behind ₹51 premium membership. |
| **Yearly Horoscope** | **IMPLEMENTED (Premium)** | Stored in `horoscopes` table (`horoscope_type = 'yearly'`). Gated behind ₹51 premium membership. |
| **Horoscope Life Facets** | **IMPLEMENTED** | Five structured sub-predictions per horoscope: Love (`love`), Career (`career`), Health (`health`), Money (`money`), Travel (`travel`). Rendered with HTML in `HoroscopeDetailScreen`. |
| **Astrological Metrics** | **IMPLEMENTED** | Lucky Number, Lucky Color, Lucky Time, and Mood stored per horoscope and displayed on `HoroscopeInfoCard`. |
| **Astrologer Directory** | **IMPLEMENTED** | Filterable directory of astrologers by expertise (`AstrologerScreen`, `astrologer_filter_controller.dart`). |
| **Astrologer Live Chat** | **IMPLEMENTED** | 1-on-1 real-time chat via Firebase Firestore (`chat_screen.dart`, `ChatController.dart`). Deducts per-minute fee from in-app wallet via `/api/{lang}/chat/deduct-fee`. |
| **Astrologer Reviews** | **PARTIALLY IMPLEMENTED** | Astrologer reviews and average ratings are calculated on backend (`AstrologerController.php#L35-L44`) and displayed in Flutter. However, there is no UI or active route to post new reviews. |
| **Astrologer Voice / Video Call**| **PARTIALLY IMPLEMENTED / PLANNED** | Database schema contains `call_minutes` and `call_price` in `astrologers` table. However, Flutter call button is commented out (`astrologer_detail_screen.dart#L307-L320`) and no WebRTC/telephony integration exists. |
| **Pooja & Ritual Booking** | **IMPLEMENTED** | Catalog of spiritual pujas with countdown timers, HTML descriptions, and Razorpay booking orders. |
| **Spiritual Donations** | **IMPLEMENTED** | Charity contributions for Temple, Cow, Food, and Education via Razorpay. |
| **User Birth Data Collection** | **PARTIALLY IMPLEMENTED** | Collects Date of Birth, Place of Birth, Time of Birth, and Gender. However, this data is never utilized for astrological computations, charts, or personalized forecasts. |
| **Chart Preference** | **PARTIALLY IMPLEMENTED** | Observable in Flutter (`preferredChartType = 'North Indian'`) and displayed on Profile, but never persisted in database (`users` table lacks `chart_preference` column). |
| **Kundli / Birth Chart Generation** | **NOT IMPLEMENTED** | No chart generation engine, SVG/Canvas chart renderer, or planetary ephemeris. |
| **Kundli Matching / Gun Milan** | **NOT IMPLEMENTED** | No compatibility or 36 Gun Milan matching logic exists. |
| **Dosha Analysis (Mangal, Kalsarpa)** | **NOT IMPLEMENTED** | No dosha calculation logic exists. |
| **Dasha & Planetary Periods** | **NOT IMPLEMENTED** | No Vimshottari or planetary period calculation logic exists. |
| **Panchang (Tithi, Nakshatra, Yoga)**| **NOT IMPLEMENTED** | No Hindu calendar or daily panchang calculation exists. |
| **Numerology** | **NOT IMPLEMENTED** | No numerology calculations exist (only static string filter chip on Astrologers). |
| **Tarot Reading** | **NOT IMPLEMENTED** | No tarot cards, deck shuffling, or card readings exist. |

---

## 5. User Journey Analysis

### 5.1 Existing User Flow
```
[App Launch]
    │
[SplashScreen] ──(First time?)──> [Language Selection (EN/HI)]
    │                                     │
[AuthPage] <──────────────────────────────┘
    ├── Has valid token? ──> [NavBarCurved (Home Tab)]
    └── No token? ─────────> [SignIn Screen]
                                  │
                 ┌────────────────┴────────────────┐
                 │                                 │
         [Email/Password]                  [Google One-Tap]
                 │                                 │
           (Must exist)                     (Auto-registers)
                 │                                 │
                 └────────► [NavBarCurved] ◄───────┘
                                  │
          ┌───────────────┬───────┴───────┬───────────────┐
          ▼               ▼               ▼               ▼
     [Home Tab]      [Poojas Tab]    [Astrologers]   [Horoscopes]
          │               │               │               │
     Daily Card       Pooja List       Directory      Sign Grid
          │               │               │               │
          ▼               ▼               ▼               ▼
    [Horoscope]     [Pooja Detail]   [Astro Detail]   (If non-today:
     Full View            │               │           Check Premium)
                          ▼               ▼               │
                    (Check Phone)   (Check Balance)       ▼
                          │               │         [PaywallScreen]
                          ▼               ▼               │
                    [Razorpay]      [ChatScreen]    (Pay ₹51 via
                                    (1-min fee      Razorpay or
                                     deductions)     Wallet)
```

### 5.2 Friction Points & Usability Issues
1. **Broken Registration Loop:** Tapping sign-up or expecting an account creation form is impossible; `sign_up.dart` is an empty container. Users without a Google account cannot register via the mobile app.
2. **Missing Birth Details Onboarding:** The app does not ask for Date of Birth, Place of Birth, or Time of Birth during sign-up. The user must manually navigate to `Profile -> Edit Profile` to fill them in.
3. **Unused Birth Details:** Even when users fill in their birth details, the app never uses them—horoscopes are generic by Sun/Moon sign, and there is no personalized Kundli or transit calculation.
4. **Phone Number Interruption During Pooja Booking:** If a user attempts to book a pooja without a saved phone number, they are suddenly redirected to `EditProfileScreen(fromBooking: true)` before they can proceed to payment.
5. **No Call Feature Despite Buttons:** The astrologer profile previously had a "Call" button that was commented out, leaving only "Chat".
6. **Chat Fee Timer Surprise:** In `ChatScreen`, if a user's wallet balance runs out during consultation, a sudden dialog pops up: `"insufficient_balance"` and immediately terminates the chat. There is no low-balance warning before automatic disconnection.
7. **Social Sharing Artifacts:** Tapping "Share" on pooja and horoscope cards shares links referring to `https://play.google.com/store/apps/details?id=com.navgarah.app` and `https://navgarah.com`, which confuses users of "Astro Setu".

---

## 6. Technical & Product Observations

### 6.1 Architectural & Code Limitations
1. **Frontend-Backend Contract Discrepancies:**
   - `chart_preference`: Flutter `AuthController` requests and expects `chart_preference` from `/api/{lang}/user-details`. The backend controller `UserController@userProfile` attempts to return `$user->chart_preference`, but the column does not exist in the `users` table or model fillable array.
   - `Astrologer Online Status`: `AstrologerChatController.php` lines 153 and 177 execute `$astrologer->is_online = ...`, whereas the column in `astrologers` table is `status` (`enum('online', 'offline')`). `is_online` only exists on the `admins` table.
   - `Review Submission`: `ReviewController@store` is implemented in PHP, but no corresponding route exists in `routes/api.php`, and no UI exists in Flutter.
2. **Abandoned Code & Dead Artifacts:**
   - `views/screens/product/product_screen.dart` is a 270-line mock product screen that is completely unreachable.
   - `views/signup_login/sign_up.dart` is an empty 90-line stub.
   - Legacy Laravel migrations, models, controllers, and admin views from a retreat booking system (`Guru`, `Promoter`, `Location`, `Cancellation`, `Inquiry`) clutter the backend.
3. **Security & Validation Gaps:**
   - `ApiController@inquiry` exposes all user inquiries publicly without authentication (`routes/api.php#L42`).
   - `config/services.php#L38` has literal string quotes around `"env('GOOGLE_REDIRECT_URI')"`.
   - `FirebaseChatController@deductFee` allows passing an arbitrary `astrologer_id` and fee deduction without validating whether an active session is currently running.
   - Legacy FCM HTTP protocol (`https://fcm.googleapis.com/fcm/send`) is used in `FirebaseChatController.php` with `env('FCM_SERVER_KEY')`, which Google has deprecated in favor of HTTP v1.
4. **Performance & Infrastructure Concerns:**
   - No background queue workers or scheduled cron tasks exist in Laravel (`Kernel.php` schedule is empty). All push notifications and email checks run synchronously in the HTTP request lifecycle.
   - Chat session fee deduction relies on client-side Flutter `Timer.periodic(const Duration(minutes: 1))` triggering an HTTP POST. If the mobile app crashes, loses connectivity, or is backgrounded, fee deduction halts while Firestore chat may remain open.

---

## 7. Complete Summary & Metrics

### Key Metrics
- **Flutter Screens Identified:** **20 screens** (17 active, 1 empty stub `SignUp`, 1 unused mock `ProductScreen`, 1 splash/loading gate `AuthPage`).
- **Implemented Astrology Features:** **6 features** (Daily Horoscope, Tomorrow Horoscope, Weekly Horoscope, Monthly Horoscope, Yearly Horoscope, Astrologer Live Chat Consultation).
- **Partially Implemented Features:** **4 features** (Astrologer Reviews, Astrologer Voice/Video Call, User Birth Details Profile, Chart Style Preference).
- **API Endpoints:** **49 distinct API routes** in `routes/api.php`.
- **Database Tables:** **30 tables** generated across 25 migration files.
- **External Integrations Identified:** **4 services** (Google OAuth & Google Client API, Firebase Firestore Real-Time DB, Firebase Cloud Messaging v1 Push, Razorpay Payment Gateway).

### Most Important Existing UX Problems
1. **Broken Registration Flow:** Complete absence of an email/password registration screen or in-app sign-up wizard.
2. **Disconnect Between Collected Profile Data and Features:** The app collects Date of Birth, Time of Birth, and Place of Birth, but does nothing with them—leaving users wondering why they entered their details.
3. **Abrupt Chat Termination:** Consultations abruptly sever when wallet funds deplete without grace periods or low-balance prompts.
4. **Rebranding Remnants:** External links, share dialogues, and Play Store URLs still reference the legacy app name "Navgarah".
5. **No Self-Serve Review System:** Users cannot review or rate astrologers after completing a consultation.

### Top 10 Opportunities for Product Improvement
1. **Integrate Real Vedic Astrology Calculations:** Implement or integrate an astrology calculation engine (or API) to generate authentic Kundli birth charts, planetary positions, Ascendant/Rasi analysis, and Vimshottari Dasha.
2. **Launch Kundli Matching & Gun Milan:** Introduce bride/groom birth detail inputs with Ashtakoot 36-point compatibility scoring, Mangal Dosha detection, and relationship recommendations.
3. **Add Daily Vedic Panchang:** Surface real-time Sunrise/Sunset, Tithi, Nakshatra, Yoga, Karana, Rahu Kaal, and Shubh Muhurat on the home screen.
4. **Implement Modern User Onboarding & Registration:** Build an intuitive authentication flow that supports Mobile OTP (SMS) and Google Sign-In, immediately followed by an engaging birth detail intake wizard.
5. **Add Numerology & Tarot Modules:** Implement Life Path number calculations, daily tarot card draws, and interactive three-card spread readings.
6. **Upgrade Astrologer Consultations with WebRTC Voice/Video Calls:** Integrate Agora, Twilio, or LiveKit to enable audio/video calls alongside the existing chat.
7. **Server-Side Consultation Management:** Move consultation session timers and wallet fee deductions from the Flutter client timer to backend Redis/queued timers or Firebase Cloud Functions to prevent fraud or lost billing.
8. **In-App Post-Consultation Reviews:** Build an automated rating dialog at the end of every chat consultation and wire it to a secure `/api/astrologer/review` endpoint.
9. **Clean Up Database & Codebase Clutter:** Safely deprecate unreferenced legacy models, migrations, and views (`gurus`, `promoters`, `locations`, `cancellations`, `product_screen.dart`), standardizing naming strictly to **Astro Setu**.
10. **Automated Push Notifications via Laravel Scheduler:** Configure cron schedules to send automated morning horoscope notifications and daily panchang updates to topic subscribers.
