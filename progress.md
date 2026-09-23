# decryption/ — Encrypt Feature Progress

Scope of this note: only the ENCRYPT feature added to the existing
`public/srfAddon/decryption/` DECRYPT tool. Not a full project doc.

## Documentation rule for this folder

For work inside `public/srfAddon/decryption/`, only update **this file**
(`progress.md`). Do **not** append to `DOC/PROJECT-UNDERSTANDING.TXT` and do
**not** regenerate `graphify-out/` — this folder is a standalone tool
outside the Mezzio app/module structure, so the main `CLAUDE.md` §8
mandatory-doc-trail rule does not apply here.

## What changed

- **`encrypt.php`** (NEW) — same layout/CSS/buttons as `index.php`, but for
  encrypting: paste raw JSON on the left, click **ENCRYPT**, ciphertext
  string appears on the right. Reuses the same project dropdown, ADD/EDIT
  key management, COPY, CLEAR. DOWNLOAD saves `encrypted.txt` instead of
  `decrypted.json`, and the output is written as-is (not
  `JSON.stringify`'d, since ciphertext isn't JSON).
- **`server.php`** — `processFinalData()` now reads `$_GET['mode']`
  (`encrypt` or default `decrypt`) and passes the matching jar flag
  (`1`=encrypt, `0`=decrypt — same convention as
  `ServiceConfigHelper::encMethod()`/`decriptMethod()` in the main app).
  Decrypt response `data` is parsed JSON; encrypt response `data` is the
  raw ciphertext string. `decrypt()` renamed to generic `runJar($data,
  $modeFlag)`.
- **`index.php`** — only addition is a DECRYPT/ENCRYPT switch link in the
  sidebar pointing to `encrypt.php`. Nothing else in this file changed.
- `add.php`, `update.php`, `getKeys.php`, `decryptionKey.json` — untouched,
  shared by both pages (same project keys work both directions, AES is
  symmetric).

## Flow

1. Pick project → paste raw JSON in `encrypt.php` → click ENCRYPT.
2. Same chunked upload as decrypt (`handleChunkUpload` → `server.php`
   `saveChunk`, 50KB chunks appended to `_tmp_chunks.txt`).
3. Final call: `server.php?getFinal=1&mode=encrypt&project_name=...` → jar
   run with flag `1` → ciphertext returned, shown in the output box as-is.
4. Decrypt side (`index.php`) is unchanged — same call without `mode`
   defaults to decrypt (flag `0`), output still `JSON.stringify`'d.

## Verification (2026-09-22)

- `php -l` clean on `encrypt.php`, `index.php`, `server.php`.
- curl round-trip against the live local vhost: encrypted a sample JSON
  payload → got ciphertext → decrypted that ciphertext back → got the
  exact original JSON.
- Checked the switch UI in a browser pane — renders/navigates correctly on
  both pages, action button label swaps as expected.

## 2026-09-22 — Deploy Tool link

- **`index.php`** and **`encrypt.php`** — added a `DEPLOY TOOL` link in the
  sidebar, right below the `CLEAR` button, pointing to
  `deploy_tool/index.html`. Opens in a new tab (`target="_blank"`) since
  it's a separate standalone tool, not part of the encrypt/decrypt flow.
  Same link added on both pages, matching the existing DECRYPT/ENCRYPT
  switch pattern already in the sidebar.

### Verification

- `php -l` clean on `index.php` and `encrypt.php`.
- Checked in a browser pane on both pages: link renders under CLEAR,
  navigates to `deploy_tool/index.html` (page title "Deploy Tool").

## 2026-09-23 — Login + role/permission (JSON users)

Pehle koi bhi URL se tool khol sakta tha, aur `decryptionKey.json`,
`getKeys.php` aur jar bhi bina login seedhe download ho jaate the. Ab har
page aur API login ke peeche hai.

- **`users.json`** (NEW) — `roles` map + `users` list (`id, username,
  password` = `password_hash`, `role`). Roles: `admin` = decrypt, encrypt,
  deploy, manage_keys, manage_users; `user` = decrypt, encrypt, deploy.
  Repo me `users` khali hai.
- **`auth.php`** (NEW) — shared include: session (httponly, SameSite=Strict),
  `loadUsers/saveUsers` (LOCK_EX), `currentUser()` (har request par file se
  re-read, isliye delete/demote turant lagta hai), `can()`,
  `requireLogin($perm, $isApi)` (page → login.php redirect, API → 401/403
  JSON), `csrfToken/checkCsrf`, `h()`.
- **`login.php`** (NEW) — login form; agar koi user nahi hai to "create
  first admin" form (koi default password nahi). `session_regenerate_id`.
- **`logout.php`** (NEW).
- **`users.php`** + **`userApi.php`** (NEW) — sirf admin. Actions `list |
  add | edit (role) | reset (password) | delete`, CSRF required. Rules:
  duplicate username block, password kam se kam 8, apna account delete
  nahi, last admin delete/demote nahi.
- **`index.php` / `encrypt.php`** — top par `requireLogin('decrypt'|'encrypt')`,
  csrf meta tag, ADD/EDIT key buttons sirf `can('manage_keys')` par,
  sidebar me USERS (admin) + LOGOUT (username). DEPLOY TOOL link ab
  `deploy_tool/` hai. Key add/update ke FormData me `csrf` jodta hai.
- **`server.php`** — final call `mode` ke hisaab se decrypt/encrypt
  permission check karta hai; chunk POST ke liye dono me se ek chahiye.
- **`add.php` / `update.php`** — `manage_keys` + CSRF. **`getKeys.php`** —
  login zaroori; non-admin ko `keys` field nahi milta (sirf dropdown names).
- **`deploy_tool/index.php`** (NEW) — auth check karke `index.html`
  serve karta hai (index.html ka naam nahi badla, deploy_tool/progress.md
  rule). **`deploy_tool/.htaccess`** — `DirectoryIndex index.php`,
  direct `index.html` aur `*.md` denied.
- **`.htaccess`** (NEW) — `*.json`, `*.txt`, `*.jar`, `*.md`, `auth.php`
  direct URL par denied, `Options -Indexes`.

### Verification (2026-09-23)

- `php -l` clean on all touched PHP files.
- curl bina session: saare pages → 302 login.php (deploy_tool se bhi
  sahi redirect); getKeys/server/add/update/userApi → 401 JSON;
  decryptionKey.json, users.json, jar, auth.php, *.md, deploy_tool/index.html
  → 403.
- curl flow: first-admin setup → admin se `testuser` add; duplicate,
  short username, self-delete, last-admin demote sab block; CSRF ke bina
  → "Invalid CSRF token". User login: ADD/EDIT/USERS render nahi hote,
  add.php/userApi → 403, users.php → 403, deploy tool 200, getKeys me
  keys nahi. `user` role se encrypt → decrypt round-trip se original JSON
  wapas mila. User delete hone ke baad uska session → 401. Logout → login.
- Browser pane: login page aur admin sidebar (ADD, EDIT, USERS, LOGOUT)
  sahi render hue. Is pane me is host ke sub-requests (CSS, fetch) ko
  pane khud block karta hai (`ERR_BLOCKED_BY_CLIENT`), isliye styling aur
  SweetAlert clicks yahan nahi dekh paaye. Wahi requests (page ke csrf
  meta token ke saath) curl se check kiye.
- Test users hata diye; `users.json` khali hai. Pehli baar kholne par
  create-admin form aayega.

### Pending / notes

- Pehla admin jo bhi pehle login.php khole wo ban sakta hai. Local vhost
  `Require local` hai, lekin server par deploy karne se pehle admin bana dena.
- `.htaccess` protection Apache `AllowOverride All` par depend karta hai
  (local vhost me hai). Server par check karna ki `decryptionKey.json` 403
  de raha hai.
- Real browser me users.php ke SweetAlert flows ek baar click karke dekhna.

## 2026-09-23 — Login ke baad wapas requested page par
- **`auth.php`** — `requireLogin()` page redirect se pehle `REQUEST_URI` ko
  `$_SESSION['auth_return']` me save karta hai. Naya `authReturnUrl()` sirf
  isi folder ke path allow karta hai (open redirect block), warna `index.php`.
- **`login.php`** — login/setup success par `authReturnUrl()` par redirect.
- Verified (curl, temp user, users.json baad me byte-same restore):
  bina login `deploy_tool/` → 302 login.php → login → 302 `deploy_tool/`
  → 200 "Deploy Tool"; logout ke baad phir 302; seedha login → index.php.

## 2026-09-23 — Passwords plain text (user request)
- User ne kaha passwords `users.json` me readable rakho. Ab `login.php`
  (first admin) aur `userApi.php` (add, reset) password plain save karte hain.
- **`auth.php`** — naya `checkPassword()`: `$2y$` se shuru ho to
  `password_verify` (purane hash rows, jaise `amit81`, chalte rahenge),
  warna `hash_equals` se plain compare. Hash ko decrypt nahi kar sakte.
  Plain chahiye to users.php se reset karo ya JSON me likh do.
- File ko web se `.htaccess` hi bachata hai (403) — ab isi par depend hai.
- Verified (curl, temp users, users.json byte-same restore): purane hash
  user ka login 302; naya user plain store hua aur login 302; galat
  password 200 + error; reset ke baad plain value store hui aur login chala.

## 2026-09-23 — Server par galat redirect fix
- Bug: server par `.../decryption/lic/srfAddon/decryption/login.php` par
  redirect ho raha tha. `authBaseUrl()` `DOCUMENT_ROOT` ki lambai se
  `__DIR__` ko kaat raha tha; server par DOCUMENT_ROOT asli path se match
  nahi karta (symlink/alias), isliye `public` ka bacha "lic" URL me aaya,
  aur aage `/` na hone se browser ne current folder ke aage jod diya.
- Fix (**`auth.php`**): `authBaseUrl()` ab `SCRIPT_NAME` (URL path) se base
  nikalta hai. Script jitne folder `auth.php` se neeche hai (jaise
  deploy_tool = 1), utne level upar jaata hai. DOCUMENT_ROOT ab use nahi hota.
- Verified: local curl — index/encrypt/users/deploy_tool sab
  `/srfAddon/decryption/login.php` par. CLI simulation jisme DOCUMENT_ROOT
  jaan-boojh ke galat diya: `/srfAddon/decryption/`,
  `/SmartPDFForm-SRF/srfAddon/decryption/`, deploy_tool se bhi sahi base.
- Server par sirf `auth.php` dobara upload karna hai.

## 2026-09-23 — Redirects fully relative (local + server dono)
- User ne kaha local aur server dono par bina config ke chale. Absolute
  URL banana hi band kar diya.
- **`auth.php`** — `authBaseUrl()` hata diya. Naye: `authScriptSubdir()`
  (filesystem se, `''` ya `deploy_tool/`) aur `authPrefix()` (`''` ya
  `../`). Redirect ab `Location: login.php` / `../login.php` deta hai,
  isliye domain, `/SmartPDFForm-*` base path ya proxy se farak nahi padta.
  `auth_return` ab relative path hai (`deploy_tool/`, `encrypt.php?x=1`).
  `authReturnUrl()` sirf relative safe paths allow karta hai: `/`, `//`,
  `http`, `..` sab `index.php` par.
- Verified: local curl — Location headers `login.php` / `../login.php`;
  login ke baad `deploy_tool/` aur `encrypt.php?x=1` par wapas; seedha
  login → index.php. Open-redirect cases (`//evil.com`, `https://evil.com`,
  `../x.php`) → index.php. users.json byte-same restore.
- Server par sirf `auth.php` upload karna hai.

## 2026-09-23 — Password reset server par save nahi ho raha tha
- Local par reset theek tha (file update, naya pwd login, purana reject).
  Server par `users.json` PHP/Apache user se writable nahi hai (FTP upload
  wali file), aur `userApi.php` `saveUsers()` ka result dekhta hi nahi tha,
  isliye fail hone par bhi "Password reset successfully" aata tha.
- **`auth.php`** — `saveUsers()` me `@file_put_contents` (warning JSON na
  tode), result bool.
- **`userApi.php`** — add/edit/reset/delete: save fail → `status:false`,
  "users.json is not writable on server, check file permission".
- **`login.php`** — first-admin setup me bhi save fail par error dikhata hai.
- Verified local: `attrib +R users.json` → reset API error deta hai;
  writable karne par success. users.json byte-same restore.
- Server fix (user ko karna hai): `users.json` ko web server user ke liye
  writable karo (jaise `chown apache:apache users.json` ya `chmod 664`).
- Pending: `add.php`/`update.php` (decryptionKey.json) bhi write result
  check nahi karte; server par wahan bhi yahi issue ho sakta hai.

## 2026-09-23 — Password rule 3 se 8 characters
- User ne kaha min 8 hatao; password sirf number, sirf letters ya mix,
  kuch bhi ho sakta hai, length 3 se 8.
- **`userApi.php`** `validPassword()` — `mb_strlen` 3..8 (add, reset).
- **`login.php`** — first-admin setup par bhi 3..8.
- **`users.php`** — add aur reset popups me `maxlength=8`, placeholder
  "3-8 chars", client-side 3..8 check.
- Login par koi length check nahi, purane lambe passwords (jaise hash
  wala `amit81`) chalte rahenge.
- Verified (curl, temp users, users.json byte-same restore): `12` reject,
  `123`, `abc`, `ab12#x`, `12345678` accept, `123456789` reject; `123` aur
  `ab12#x` se login chala; reset `99` reject, `999` accept + login chala.

## 2026-09-23 — auth.php ab dusre tools bhi use karte hain (Axis Dummy Data)
- `public/srfAddon/axisDummyData/` isi login/`users.json` ko share karta hai
  (details: DOC/PROJECT-UNDERSTANDING.TXT entry #47).
- **Naye perms**: admin += `dummy_view`, `dummy_manage`; user += `dummy_view`.
  `loadUsers()` default perms ko stored roles me merge karta hai, isliye
  server ki `users.json` manually edit nahi karni.
- **Redirect**: `authScriptSubdir()` hata ke `authRelPath(from, to)` +
  `authScriptDir()`. `authPrefix()` kisi bhi folder se sahi relative path
  deta hai (`''`, `../`, `../decryption/`). `authReturnUrl()` shuru ke
  `../` allow karta hai; `/`, `//`, scheme, beech ka `..` → `index.php`.
- Verified: decryption `index.php` → `login.php`, `deploy_tool/` →
  `../login.php` pehle jaise; axisDummyData se login ke baad wapas wahin.

## 2026-09-23 — toolHub: sab tools alag folders + common + card index
Is file ki purani entries `public/srfAddon/decryption/` ke time ki hain; wo
folder ab `public/srfAddon/toolHub/` ban gaya. URL: `/srfAddon/toolHub/`.

```
toolHub/
  index.php        card index (public; login status dikhata)
  .htaccess        *.json *.txt *.jar *.md + auth.php deny, no listing
  common/          auth.php login.php logout.php users.json
    assets/        bootstrap.min.css sweetalert2.js hub.css
  crypto/          index.php (Decrypt) encrypt.php server.php add/update/getKeys.php
                   decryptionKey.json aes_enc_omini.jar *.svg
  deploy/          index.php (auth) + index.html deploy_tool.css/.js .htaccess
  dummyData/       index.php axisDummyData.css axisDummyData.js (DB tool)
  users/           index.php (pehle users.php) userApi.php
```
- Purane `/srfAddon/decryption/*` aur `/srfAddon/axisDummyData/` hata diye
  (git mv). `decryption/` ka khali folder session cwd hone se abhi bacha hai,
  git me nahi hai — baad me delete karna.
- **Card index** (`index.php`): Decrypt, Encrypt (alag cards, same `crypto/`),
  Deploy Tool, Axis Addon Dummy Data, User Management (sirf `manage_users`).
  Logged out → "Login required" badge + header me Login; logged in → naam·role
  + Logout; perm nahi → card disabled "No access".
- **Card click flow**: tool page `requireLogin()` → `../common/login.php`,
  `auth_return` = `../crypto/` jaisa → login ke baad wapas usi tool par.
  Seedha login / logout → hub.
- **common/auth.php**: naya `authHubUrl()` (calling page se hub ka relative
  path). `authReturnUrl()` fallback + login.php/logout.php redirect ab hub.
  403 page par "Back to Tool Hub" link.
- **Back arrow** `.hub-back` (common/assets/hub.css) har jagah `../` par:
  crypto dono pages (sidebar top), deploy (header ke upar), dummyData (hero
  left, on-dark), users (title ke saath), login (floating top-left).
- Login page: blue gradient + "Tool Hub Login", button blue.
- Paths: crypto → `../common/auth.php`, `../common/assets/...`, USERS
  `../users/`, LOGOUT `../common/logout.php`, DEPLOY `../deploy/`.
  dummyData → `chdir(dirname(__DIR__, 4))`, Users `../users/`.

### Verification (2026-09-23)
- `php -l` sab 14 PHP files clean.
- curl bina login: hub 200 (4 cards "Login required", Users card nahi);
  crypto/, crypto/encrypt.php, deploy/, dummyData/, users/ → 302
  `../common/login.php`; APIs 401; common/users.json, crypto/decryptionKey.json,
  jar, common/auth.php, *.md, deploy/index.html, common/ → 403; assets 200;
  purane URLs 404.
- Temp users (users.json byte-same restore): har card se login → wapas usi
  tool par; seedha login → hub; user → 4 tools 200, users/ 403 (hub + logout
  links); crypto encrypt→decrypt round-trip same; dummyData list ok; logout →
  hub. Admin → hub par User Management card + Admin badge; userApi list ok;
  har tool par back arrow `../`. Koi DB write nahi.
- Browser preview (inline CSS, localTemp, baad me delete): hub logged-out +
  admin, login, crypto, deploy, dummyData; hub 375px no horizontal scroll.

### Server par
- `decryption/users.json` (amit81) → `toolHub/common/users.json` copy karna,
  warna first-admin setup dobara aayega. File web user ke liye writable.

## 2026-09-23 — Hub page sizes normal
- User ne kaha main page ka font / card size normal karo.
- **`common/assets/hub.css`** (sirf hub classes, back arrow same): base font
  13 → 12.5px; header padding 22/24 → 14/18px, title 21 → 17px, logo 48 → 38px;
  card padding 18 → 14px, icon 42 → 34px, title 15 → 13.5px, text 12px,
  badges 11px; grid `minmax(250px, 1fr)` → `minmax(210px, 1fr)`, gap 14px;
  page max-width 1180 → 1100px. Mobile title 16px, logo 34px.
- Verified (preview, 1366px): header 72px, card 265×~160, 4 cards per row,
  cards ka right edge header ke saath align (1233 = 1233). Preview delete.

## 2026-09-23 — dummyData se Users link hataya
- User ne kaha dummyData page se user ka option hatao.
- **`dummyData/index.php`** header se admin wala "Users" button (`../users/`)
  hata diya. User Management ab sirf hub ke card se khulta hai.
- Verified (temp admin, users.json restore): page 200, Users link 0; naam
  chip, Logout, Tool Hub back arrow, Add New Record pehle jaise.
