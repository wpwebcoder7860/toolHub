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

## 2026-09-24 — Module-wise (per-user) access control + Super Admin tier

User ne kaha: role-wide fixed permission list nahi, har user ke liye
alag se decide karna hai kaunse cards/actions milenge; ek Super Admin
tier chahiye jiska access kabhi kam na ho. Sab kuch `users.json` file
me — koi naya DB table nahi (dummy data khud DB se aata hai, alag cheez).

- **`common/auth.php`**:
  - Naya `PERMISSION_MODULES` const — 7 assignable keys: `decrypt`,
    `encrypt`, `deploy`, `dummy_view`, `dummy_add`, `dummy_update`,
    `dummy_delete` (label + group, Users UI checklist isi se banta hai).
    `manage_keys` / `manage_users` isme nahi — ye derived/reserved hain.
  - `authDefaultStore()['roles']` ab sirf label list hai:
    `super_admin` (naya) / `admin` / `user`, sab khali arrays — role ab
    perm-list carry nahi karta.
  - `loadUsers()` — legacy user rows (jinke paas `permissions` key nahi
    hai) ko purane fixed role-list (`AUTH_LEGACY_ROLE_PERMS`) se derive
    karke in-memory `permissions` map deta hai — purana `users.json`
    (jaise `amit81`/admin) bina kisi manual edit ke chalta rehta hai.
    Ye sirf read-time derivation hai, file par likha nahi jaata jab tak
    koi aur write action (add/edit) na ho.
  - `can($perm)` rewrite: `role === 'super_admin'` → hamesha `true`
    (code-level, `users.json` se ye chhina nahi ja sakta). `manage_users`
    → sirf super_admin (`false` sabke liye baaki). `manage_keys` →
    derived: `decrypt` ya `encrypt` me se koi ek true ho to true. Baaki
    saare perm keys seedhe `$user['permissions'][$perm]` se.
- **`users.json`** naya user-record shape: `permissions: {decrypt,
  encrypt, deploy, dummy_view, dummy_add, dummy_update, dummy_delete}`
  (bool map, per-user). `super_admin` records `permissions: {}` rakhte
  hain (ignore hota hai, bypass already hai).
- **`users/userApi.php`**: `adminCount()` → `superAdminCount()`, "last
  admin" guard ab "last super_admin" par hai (demote/delete dono me).
  Naya `sanitizePermissions($role)` — `$_POST['permissions']` (JSON)
  ko `PERMISSION_MODULES` keys ke against whitelist + bool coerce karta
  hai; `role === super_admin` par khali array force karta hai. `add`
  aur `edit` dono actions ab `permissions` bhi save karte hain. `list`
  response me `permissions` per user aur `modules: PERMISSION_MODULES`
  bhi jaata hai (UI checklist render karne ke liye).
- **`users/index.php`**: ROLE button → **ACCESS** button. Ek hi dialog
  me role `<select>` + `PERMISSION_MODULES` se grouped checkboxes
  (Crypto: Decrypt/Encrypt · Deploy · Axis Dummy Data: View/Add/Update/
  Delete). Role `super_admin` select karne par checklist hide ho jaati
  hai + "Super Admin gets full access automatically" note. Add User
  dialog me bhi same checklist.
- **`dummyData/index.php`**: action-level gate split — `list` →
  `dummy_view`, `insert` → `dummy_add`, `update`/`toggle` →
  `dummy_update`, `delete` → `dummy_delete` (pehle sab `dummy_manage`
  par the). Page render me `$canAdd/$canUpdate/$canDelete` (purana
  `$canManage` = teeno ka OR, sirf backward-compat ke liye). Body par
  `data-can-add/-update/-delete` attrs. Add button + insert modal →
  `$canAdd`. Submit Changes button + view/edit title → `$canUpdate`.
  Delete modal → `$canDelete`.
- **`dummyData/axisDummyData.js`**: `canAdd/canUpdate/canDelete` naye
  fields (body dataset se). `bindInsert()` ab `canAdd` par gate hai.
  Row actions: edit icon `canUpdate`, delete icon `canDelete`, status
  toggle `canUpdate`. View modal read-only state `canUpdate` se decide
  hota hai (pehle `canManage`).
- **`common/login.php`**: first-account setup (jab `users.json` khali
  ho) ab `role: 'super_admin'` banata hai (pehle `admin`), taaki sole
  account kabhi lock-out na ho. Heading/button text "Super Admin"
  reflect karte hain.
- Baaki tools (`crypto/*`, `deploy/index.php`, hub `index.php`) me koi
  code change nahi — sab `requireLogin()`/`can()` se hi kaam karte the,
  naya `can()` unke liye transparently kaam karta hai.

### Verification (2026-09-24)
- `php -l` clean on `common/auth.php`, `common/login.php`,
  `users/index.php`, `users/userApi.php`, `dummyData/index.php`.
- `node --check` clean on `dummyData/axisDummyData.js` aur
  `users/index.php` ke inline `<script>` block.
- Read-only PHP test (`common/auth.php` seedha `require`, koi
  `saveUsers()` call nahi): live `users.json` (`amit81`, role=admin,
  koi `permissions` key nahi) ko `loadUsers()` se load karke check kiya
  — migration ne sab 7 legacy perms `true` derive kiye, jaisa expect
  tha. `can()` ki logic (super_admin bypass, `manage_keys` derivation,
  `manage_users` sirf super_admin) manually verify ki. Test se pehle
  `users.json` ka backup liya, baad me `diff` se confirm kiya file
  byte-identical hai — koi write nahi hua.
- Browser/curl-based full login-flow round-trip (naya super_admin se
  ek scoped admin banana, uske access se hub/dummyData check karna)
  is session me nahi kiya — sirf read-only verification kiya gaya.

### Pending / notes
- Full click-through (Users UI checklist save/load, dummyData ke Add/
  Edit/Delete buttons ek real logged-in scoped user se) abhi browser me
  dekhna baaki hai.

## 2026-09-24 — amit81 → Super Admin; Status toggle apna permission + no-access = section/card hidden

- `common/users.json`: `amit81` ka `role` `admin` se `super_admin` kar
  diya (`permissions: []`, code-level bypass se access), `roles` map
  naye 3-role format (`super_admin/admin/user`, khali arrays) me
  update kiya. Manual file edit tha, DB write nahi, user ki seedhi
  request thi.
- **Naya `dummy_status` permission** (Status ON/OFF toggle ab
  `dummy_update` se alag): `common/auth.php` — `PERMISSION_MODULES` me
  `dummy_status` (group "Axis Dummy Data") joda; `AUTH_LEGACY_ROLE_PERMS`
  me legacy `admin` ke liye bhi joda (auto-migration full access
  rakhe). Users UI (`users/index.php`) me checklist dynamically
  `PERMISSION_MODULES` se banti hai, isliye koi UI code change nahi
  laga — checkbox apne aap aa gaya.
- **`dummyData/index.php`**: `toggle` action ab `dummy_status` se gate
  hai (pehle `dummy_update` share karta tha). `$canStatus = can('dummy_status')`.
- **No access = section/card gayab, disabled/text nahi dikhta** (user
  ne explicitly maanga — pehle disabled switch / "View only" text tha):
  - `dummyData/index.php`: Active/Inactive stat cards, table ka Status
    `<th>` aur Actions `<th>` — sab `$canStatus` / `$canUpdate||$canDelete`
    ke peeche conditional. `statusNotice` bhi `$canStatus` ke peeche.
    Loading row ka `colspan` ab dynamic (`7 + status? + actions?`).
  - `axisDummyData.js`: `rowHtml()` ab status/actions `<td>` bilkul
    render hi nahi karta jab access nahi (pehle disabled switch ya
    "View only"/muted text tha). `BASE_COLS=7` + `this.colCount`
    (instance property) puraane global `COLS=9` constant ki jagah — sab
    `colspan` isi se aate hain taaki empty/loading/error rows sahi
    span karein jitne columns us user ko dikh rahe hain.
  - **`index.php`** (hub): pehle non-adminOnly card sabko dikhta tha
    (`disabled` class + "No access for your role" text); ab logged-in
    user ke liye `can($t['perm'])` false hone par card poora hi skip
    ho jata hai (`continue`) — jaisa pehle sirf `adminOnly` cards ke
    liye tha, ab sab cards ke liye. Anonymous (not logged in) visitor
    ko sab cards "Login required" ke saath pehle jaise hi dikhte hain.

### Verification (2026-09-24, dusra round)
- `php -l` clean: `index.php`, `common/auth.php`, `dummyData/index.php`.
- `node --check` clean: `dummyData/axisDummyData.js`.
- Read-only test (`loadUsers()` seedha call, koi `saveUsers()` nahi):
  `amit81` → `role: super_admin`; `PERMISSION_MODULES` me `dummy_status`
  present; `shivanshu` (existing test user) ke paas `dummy_status` key
  hi nahi (missing = false), matlab uska status column ab table me
  bilkul nahi dikhega.
- `users.json` md5 checksum verification se pehle aur baad me same
  raha (sirf `amit81` role wala manual edit hua, baaki kaam read-only
  tha) — koi accidental write nahi.
- Live browser click-through abhi bhi pending hai (neeche note).

## 2026-09-24 — Bug fix: ACCESS button click par JSON.parse crash

User ne browser console error diya: `Uncaught SyntaxError: Expected
property name or '}' in JSON ... at UserManager.editAccess`.

- **Root cause**: `users/index.php` row render me `data-perm="${this.esc(JSON.stringify(u.permissions))}"`
  — `esc()` `div.textContent`/`innerHTML` se HTML-escape karta hai, jo
  sirf `&`/`<`/`>` escape karta hai, **`"` nahi** (text-node context me
  zaroorat nahi hoti). JSON string me `"` hoti hi hai, to wo as-is
  double-quoted HTML attribute ke andar chali gayi aur attribute pehle
  hi `"` par khatam ho gaya — browser ko sirf `data-perm="{"` mila,
  baaki JSON garbage ban gaya. `JSON.parse('{')` isi error ke saath
  fail karta hai jo user ne dekhi — reproduce karke confirm kiya
  (`node` se simulate kiya, exact error message match hua).
- **Fix (`users/index.php`)**: attribute me JSON ab `encodeURIComponent()`
  se likha jaata hai (`"`/`<`/`>`/`&` in se kisi ka use nahi, attribute
  kabhi break nahi hota). `editAccess()` me padhte waqt
  `JSON.parse(decodeURIComponent(perm))`, `try/catch` ke saath (corrupt/
  missing data par khali `{}` fallback).
- Verified: `php -l` clean, inline `<script>` `node --check` clean;
  `node` se exact old-vs-new attribute encoding simulate kiya — purana
  code wahi error deta hai jo user ne report ki, naya code sahi JSON
  wapas deta hai. `users.json` isme touch nahi hua (checksum same).

## 2026-09-24 — Bug fix: dummyData load par "Cannot set properties of null"

User ne error di: `Cannot set properties of null (setting 'textContent')`
dummyData page load hote hi.

- **Root cause (`dummyData/axisDummyData.js`)**: pichhle change me Status
  column ke saath Active/Inactive stat cards bhi `$canStatus` ke peeche
  conditional bana diye the (`dummyData/index.php`), lekin
  `renderStats()` — jo har `list` fetch (yaani page load) ke baad chalta
  hai — bina check kiye `$('statActive').textContent = ...` aur
  `$('statInactive').textContent = ...` kar raha tha. Jis user ke paas
  `dummy_status` permission nahi hai, uske liye ye elements DOM me hote
  hi nahi (PHP unhe render nahi karta), to `$('statActive')` = `null`
  aur `.textContent` set karte hi crash.
- **Fix**: `renderStats()` me `if (!this.canStatus) return;` (Total
  Records set karne ke baad, Active/Inactive set karne se pehle).
  Isi tarah `confirmDelete()` me stat-decrement wala block bhi
  `this.canStatus &&` se guard kiya (pehle sirf `this.hasStatus` check
  tha, jo `dummy_status` na hone par bhi true ho sakta hai agar DB me
  column exist karta hai).
- Baaki saare `$('id')` lookups is file me check kiye — jo bhi
  conditionally-rendered element access karte hain (insert modal, delete
  modal, submit-changes button) wo sab apne matching perm ke event
  listener ke andar hi chalte hain (jaise `bindInsert()` sirf `canAdd`
  par bindhta hai), isliye waha koi aur null-crash risk nahi mila.
- Verified: `node --check` clean. Root cause manually trace karke
  confirm kiya — Status column hide hone wale pichhle change ne hi ye
  regression introduce kiya tha, isi session me pakda aur fix kiya.
  `users.json` isme touch nahi hua.

## 2026-09-24 — Status column wapas revert (hide → badge/toggle), + horizontal scroll

User ne 2 cheezein maangi: (1) table me left-right scroll karne ka
tareeka, (2) Status column ko poori tarah hide karne ke bajaye — jisko
`dummy_status` access nahi hai use **Active/Inactive badge** (read-only
text) dikhao, jisko access hai use asli **toggle switch**. Yani pichhle
"column hi hide karo" wale change ko sirf Status ke liye revert kiya
(Actions column ka hide-if-no-access behavior waisa hi hai).

- **`dummyData/index.php`**: `Status` `<th>`, Active/Inactive stat
  cards, aur `statusNotice` — teeno ab **hamesha** render hote hain
  (`$canStatus` conditional hata diya). Sirf Actions `<th>` abhi bhi
  `$canUpdate||$canDelete` ke peeche hai. Loading row ka colspan
  `8 + (actions?1:0)` (Status ab hamesha count hoti hai).
- **`dummyData/axisDummyData.js`**: `rowHtml()` — `dummy_status` ho to
  toggle switch; na ho lekin DB me status column ho to read-only
  **badge** (`Active`/`Inactive` text, "View only" title); status column
  hi na ho DB me to disabled switch ("Run the status SQL to enable"),
  jaisa pehle (naye access ke saath sirf badge → switch replace hota
  hai). `renderStats()` aur `confirmDelete()` ke `canStatus` guards hata
  diye (stat cards ab hamesha DOM me hain).
- **Horizontal scroll**: table-top me 2 chhote **‹ ›** buttons
  (`scrollLeftBtn`/`scrollRightBtn`, 240px smooth `scrollBy`) — table
  bahut wide hai (`min-width:1000px`) is se buttons se explicit left/
  right scroll milta hai. `axisDummyData.css` me `.table-wrap` ka
  scrollbar bhi visible/thick style diya (webkit + firefox
  `scrollbar-width`) taaki neeche horizontal scrollbar dikhe bhi, na
  sirf drag se scroll ho.

### Verification (2026-09-24)
- `php -l` clean `dummyData/index.php`; `node --check` clean
  `axisDummyData.js`.
- `users.json` is session ke dauraan hi live app se update hua (Users
  UI se `shivanshu` par ACCESS save — `dummy_status:false` add hua) —
  ye expected live-testing write hai, maine touch nahi kiya, sirf
  checksum-diff se confirm kiya ki ye external write thi.

## 2026-09-24 — "Response Status" column hataya

User ne "Response Status" column (accountServiceRes valid/non-empty
JSON hai ya nahi, Success/Failed badge) poochha, phir "hata do" bola.

- **`dummyData/index.php`**: `SORTABLE` se `responseStatus` hataya,
  `RESPONSE_OK_SQL` const hataya, `list` action ka sort `$orderBy`
  ternary hataya (ab seedha `$sort` use hota hai). `<th data-sort=
  "responseStatus">Response Status</th>` hataya. Loading row colspan
  `8` → `7` (ab base columns: #, Account Number, Title, Description,
  Response, Creation Date, Status).
- **`dummyData/axisDummyData.js`**: `isResponseOk()` function aur uska
  call (`rowHtml()` me) hataya, Response Status `<td>` hataya,
  `BASE_COLS` `8` → `7`.
- `.badge-success`/`.badge-failed` CSS classes touch nahi kiye — Active/
  Inactive badge (Status column ka read-only view) inhi classes ko
  reuse karta hai.
- `accountServiceRes`/`demographicServiceRes` data columns aur "View
  Response" button waisa hi hai — sirf derived Success/Failed summary
  column hata hai, underlying data nahi.

### Verification
- `php -l` clean `dummyData/index.php`; `node --check` clean
  `axisDummyData.js`. Grep se confirm kiya `responseStatus`/
  `isResponseOk` ka koi reference nahi bacha.
- `users.json` isme touch nahi hua (checksum-diff sirf live app
  testing se aaya, is change se nahi).

## 2026-09-24 — Account Number / Title / Description inline-editable

User ne kaha ye teen columns table me hi editable hon, jisko edit
access mila hai usi ke liye.

- **`dummyData/index.php`**: `update` action me naya block —
  `accountNumber`, `title`, `description` (plain text, JSON validation
  nahi lagti unpe, sirf trim + empty→null) `accountServiceRes`/
  `demographicServiceRes` wale JSON-validated block ke saath-saath
  save hote hain, same `update` endpoint se, sirf jo field POST me ho
  wahi update hoti hai.
- **`dummyData/axisDummyData.js`**: naya `editableCell(value, field)`
  helper — `canUpdate` true ho to `contenteditable="true"` `<td
  data-field="...">` (dashed underline hover, click karke edit),
  warna pehle jaisa plain read-only `<td>`. Naya `bindInlineEdit()`
  (sirf `canUpdate` par bind hota hai) — `focusin` par original value
  save karta hai, `Enter` = save (blur trigger), `Escape` = revert
  (bina save), `focusout` par `saveInlineEdit()` — value badla ho tabhi
  `update` action call, fail hone par purani value wapas aur error
  toast, success par row cache + `title` attr update.
- **`axisDummyData.css`**: `.editable-cell` — hover par dashed border,
  focus par outline + wrap-to-full (taaki lambi description edit karte
  waqt cut na ho), `.saving` state par dimmed + pointer-events off.
- View/Edit Response modal (accountServiceRes/demographicServiceRes
  JSON) waisa hi hai, alag se — dono edit paths ek hi `update` action
  share karte hain lekin independent fields par.

### Verification
- `php -l` clean `dummyData/index.php`; `node --check` clean
  `axisDummyData.js`.
- Logic manually trace kiya: `list` action pehle se hi
  accountNumber/title/description poori row ke saath deta hai (koi
  column-restriction nahi), to naya UI seedha use kar sakta hai.
  `update` action naya block sirf POST me maujood field update karta
  hai (`array_key_exists` check), baaki columns untouched rehte hain.
- `users.json` isme touch nahi hua.

## 2026-09-24 — Inline edit: hover-icon se open (click-anywhere nahi)

User ne kaha: puri cell click karke edit shuru na ho, hover par pencil
icon aaye aur usi par click karne se edit khule.

- **`dummyData/axisDummyData.js`**: `editableCell()` ab td ke andar
  `<span class="cell-text">` (text) + `<button data-act="edit-cell">`
  (pencil icon, `#i-edit` svg reuse — Actions column wala hi symbol)
  deta hai. Naya `startInlineEdit(td)` — icon click par `cell-text`
  span ko `contentEditable=true` karta hai, focus + poora text
  select kar deta hai (turant overwrite type kar sako). `bindInlineEdit()`
  ab `.cell-text[contenteditable="true"]` par hi `Enter`(save)/`Escape`
  (revert) sunta hai, `focusout` par `saveInlineEdit(span)` — save ke
  baad `contentEditable=false` wapas. `bindTable()` click handler me
  `data-act === 'edit-cell'` ka naya branch.
- **`axisDummyData.css`**: `.editable-cell` ab flex container (text +
  icon), `.cell-edit-btn` default `opacity:0`, `.editable-cell:hover`
  par `opacity:1` (sirf usi cell ka icon dikhta hai, poori row ka nahi).
  Editing state (`contenteditable="true"` wale span) par outline +
  white-space normal (poora text dikhe).
- Backend (`update` action) me koi change nahi tha — pehle se hi teeno
  fields (`accountNumber`/`title`/`description`) support karta tha.

### Verification
- `php -l` clean, `node --check` clean.
- `users.json` isme touch nahi hua.

## 2026-09-24 — Decrypt/Encrypt/Deploy sabke liye universal, per-user gating sirf Dummy Data par

User ne kaha: Decrypt, Encrypt, Deploy card ka access har logged-in
user ko mile (role/permission se independent) — per-user checklist
sirf **Axis Dummy Data** ke liye rahe.

- **`common/auth.php`**:
  - Naya `AUTH_UNIVERSAL_PERMS = ['decrypt', 'encrypt', 'deploy',
    'manage_keys']` — `can()` me `super_admin` check ke turant baad,
    in 4 perms ke liye seedha `true` (koi bhi logged-in user, role ya
    stored `permissions` se farak nahi padta). `manage_keys` bhi isi
    list me hai (pehle `decrypt||encrypt` se derive hota tha, ab wo
    khud universal hain to seedha universal rakh diya — same result).
  - `PERMISSION_MODULES` se `decrypt`/`encrypt`/`deploy` hata diye —
    ab sirf `dummy_view/add/update/delete/status` (5 keys) bachi hain.
    Users UI (`users/index.php`) ka checklist server se `modules` fetch
    karke dynamically banta hai, isliye UI code change nahi laga —
    checklist se Crypto/Deploy checkbox apne aap gayab ho gaye, sirf
    Axis Dummy Data group dikhta hai.
  - `AUTH_LEGACY_ROLE_PERMS` se bhi `decrypt/encrypt/deploy` hata diye
    (sirf dummy_* legacy migration ke liye reh gaye) — harmless cleanup,
    kyunki `can()` ab wahan tak pahunchta hi nahi un teeno perms ke liye.
  - `manage_users` abhi bhi sirf super_admin (nahi badla).
- Koi aur file touch nahi hui — hub `index.php` ka card-hide logic
  (`can($t['perm'])`), `crypto/*` ka `requireLogin`/`can()`,
  `deploy/index.php` sab already isi `can()` function se kaam karte
  the, naya universal-bypass unke liye transparently kaam karta hai.

### Verification
- `php -l` clean `common/auth.php`.
- Read-only test (`loadUsers()` seedha, koi `saveUsers()` nahi): ek
  khali-permissions wale `user` role ke liye `decrypt/encrypt/deploy/
  manage_keys` sab `true` aaye, `dummy_view`/`manage_users` `false`
  (jaisa expect tha). Live `shivanshu` record (jisme `decrypt:true,
  encrypt:false, deploy:true` stored hai) — teeno ke liye `can()` ab
  `true` deta hai (stored value se independent, universal bypass).
  `PERMISSION_MODULES` me ab sirf 5 dummy_* keys hain.
- `users.json` isme touch nahi hua (checksum same).

## 2026-09-24 — Bug fix: table columns bigad gaye the (inline-edit ka side effect)

User ne screenshot diya — Account Number column me 3 values stack ho
rahi thi, baaki columns shift/khali ho gaye the.

- **Root cause**: hover-icon inline-edit change me `.editable-cell`
  (jo khud `<td>` hai) par `display: flex` laga diya tha. `<td>` par
  `display:flex` lagane se browser use table ke column-width sync
  algorithm se nikal deta hai — har row ka flex-`<td>` apne content ke
  hisaab se independently size hota hai, baaki rows/columns ke saath
  align nahi hota, isliye columns visually toot-phoot gaye (bina DOM
  structure galat hue).
- **Fix**: `<td class="editable-cell">` ab normal table-cell hi rehta
  hai (`overflow:hidden` ke alawa koi display override nahi). Naya
  inner `<span class="cell-inline">` (text + edit-icon) par
  `display:flex` move kar diya — flex layout ab td ke andar ek span
  tak simit hai, td khud table layout algorithm me hi rehta hai.
  **`dummyData/axisDummyData.js`** (`editableCell()`) aur
  **`axisDummyData.css`** dono me change.
- JS logic (`startInlineEdit`, `saveInlineEdit`) me koi change nahi
  laga — `querySelector('.cell-text')` aur `closest('.editable-cell')`
  extra nesting ke saath bhi waise hi kaam karte hain.

### Verification
- `node --check` clean.
- `users.json` isme touch nahi hua.

## 2026-09-24 — User Management ab 'admin' role ko bhi grant ho sakta hai

User ne confirm kiya (AskUserQuestion se): Dummy Data per-user hi rahe
(koi role-restriction nahi, jaisa abhi hai); lekin **Manage Users**
sirf Super Admin tak seemit na ho — Super Admin ab `admin` role wale
kisi user ko bhi ye checkbox de sake. `user` role aur logged-out public
ko kabhi nahi.

- **`common/auth.php`**: `PERMISSION_MODULES` me naya `manage_users`
  key (group "Admin") — ab checklist me checkbox ke roop me dikhta hai.
  `can()` me `manage_users` ab: `role === 'admin' && permissions
  ['manage_users'] === true` (super_admin ke liye upar hi `true` mil
  chuka hota hai bypass se; `role === 'user'` ke liye hamesha `false`
  hai, chahe stored data me kuch bhi ho — defense in depth). Legacy
  migration list (`AUTH_LEGACY_ROLE_PERMS['admin']`) me bhi `manage_users`
  joda taaki purana admin account upgrade par ye access na khoye.
- **`users/userApi.php`**: `sanitizePermissions()` — `role !== 'admin'`
  (matlab `user`) par `manage_users` ko forcibly `false` kar deta hai,
  save hone se pehle hi (galat state kabhi persist nahi hoti).
- **`users/index.php`**: `toggleAccessBox(role)` ab "Manage Users"
  checkbox ki row bhi handle karta hai — `role === 'admin'` par hi
  dikhti hai, `user`/`super_admin` select karne par hide + uncheck ho
  jaati hai (super_admin ke liye poora checklist hi hide hai, admin ke
  liye sirf ye ek row). Add User dialog me bhi initial `toggleAccessBox`
  call add kiya (pehle sirf role-change par chalta tha, open hote hi
  nahi — minor pehle se maujood gap fix ho gaya).
- Hub `index.php` ka card-hide logic (`can($t['perm'])`) already
  generic hai, koi change nahi laga — `manage_users` true hote hi
  Users card admin ko bhi dikhega.

### Verification
- `php -l` clean `common/auth.php`, `users/userApi.php`,
  `users/index.php`; inline `<script>` `node --check` clean.
- Read-only test (`loadUsers()`, koi write nahi): `super_admin` →
  `can(manage_users)=true`; `admin` + `manage_users:true` → `true`;
  `admin` + `manage_users:false` → `false`; `user` + tampered
  `manage_users:true` (jaanbujh kar galat data) → phir bhi `false`.
  `PERMISSION_MODULES` me `manage_users` present. `amit81` abhi bhi
  `super_admin` hai, unaffected.
- `users.json` isme touch nahi hua (checksum same).

## 2026-09-24 — Decrypt/Encrypt/Deploy Tool ab public (no login)

User ne confirm kiya (AskUserQuestion, security-sensitive change hone
ki wajah se pooch ke): Decrypt, Encrypt aur Deploy Tool teeno tools ab
**bina login ke bhi seedhe khulenge** — koi bhi (public internet se
bhi) direct URL se use kar sakega. Dummy Data aur User Management
abhi bhi login maangte hain, unme koi change nahi.

- **`crypto/index.php`**, **`crypto/encrypt.php`**: `requireLogin(...)`
  → `currentUser()` (login zaroori nahi, sirf pata karta hai koi logged
  in hai ya nahi). Sidebar ka LOGOUT button ab sirf `$me !== null` par
  dikhta hai; anonymous visitor ko uski jagah **LOGIN** link milta hai
  (dusre tools ke liye login karna ho to).
- **`crypto/server.php`**: `getFinal`/chunk-upload wala `requireLogin`
  block poora hata diya — encrypt/decrypt jar-run ab kisi bhi visitor
  ke liye chalta hai.
- **`crypto/getKeys.php`**: `requireLogin(null, true)` hataya — **zaroori
  tha** kyunki page ab public hai aur iska "PRODUCT NAME" dropdown isi
  endpoint se aata hai; login required rehta to anonymous user ke liye
  page load hote hi dropdown khali/broken hota. Raw key data abhi bhi
  safe hai — `$showKeys = can('manage_keys')` waisa hi hai, anonymous
  ko sirf project names milte hain, actual keys nahi.
- **`crypto/add.php`/`update.php`**: koi change nahi — `manage_keys`
  ke peeche hi rahenge (login + permission dono chahiye), aur ADD/EDIT
  buttons khud UI me `can("manage_keys")` ke peeche chhupe hain, to
  anonymous ko wo dikhte hi nahi.
- **`deploy/index.php`**: `requireLogin('deploy')` hataya — page ab
  public hai. `deploy_tool.js` khud kisi server endpoint ko call nahi
  karta (pure client-side command generator), isliye koi aur dependency
  nahi thi.
- **`index.php`** (hub): `$tools` array me Decrypt/Encrypt/Deploy par
  naya `'public' => true` flag. Loop me `$isPublic` check — public
  cards na kabhi hide hote hain (chahe login ho ya na ho), na "Login
  required" badge dikhata hai, seedha "Open" state me rehta hai. Dummy
  Data aur Users card logic waisa hi hai (login + permission dono
  chahiye). Footer text bhi update kiya naya behavior reflect karne ke
  liye.

### Verification
- `php -l` clean: `index.php`, `crypto/index.php`, `crypto/encrypt.php`,
  `crypto/server.php`, `crypto/getKeys.php`, `deploy/index.php`.
- Manually trace kiya: `crypto/*` aur `deploy/index.php` ke andar ab
  koi `requireLogin` call nahi bacha (grep se confirm). `getKeys.php`
  ka `showKeys` gate abhi bhi `manage_keys` par hai, isliye raw keys
  expose nahi hoti.
- `users.json` isme touch nahi hua.

### Pending / notes
- Live browser se end-to-end confirm karna baaki hai (private/incognito
  window me bina login ke crypto/ aur deploy/ khol ke dekhna).

## 2026-09-24 — crypto sidebar se DEPLOY TOOL aur LOGIN button hataye

User ne kaha decrypt/encrypt sidebar se Deploy Tool ka link aur (pichle
public-access change me maine jo anonymous ke liye add kiya tha)
LOGIN button bhi hata do.

- **`crypto/index.php`**, **`crypto/encrypt.php`**: `<a href="../deploy/">
  DEPLOY TOOL</a>` dono se hataya. Anonymous visitor ke liye jo LOGIN
  link add kiya tha (pichle entry me), wo bhi hata diya — ab sidebar
  me sirf `$me !== null` hone par LOGOUT dikhta hai, anonymous user ke
  liye login/logout section hi khali (koi button nahi).
- Deploy Tool khud hub card se abhi bhi accessible hai — sirf crypto
  pages ke sidebar se link hataya, `deploy/` route/page waisa hi hai.

### Verification
- `php -l` clean dono files. Grep se confirm kiya `deploy/` aur `LOGIN`
  ka koi reference crypto pages me nahi bacha.
- `users.json` isme touch nahi hua.

## 2026-09-24 — Key ADD/EDIT bhi global (decrypt/encrypt jaisa)

User ne kaha ADD/EDIT key wala button bhi global rahega, sirf Decrypt/
Encrypt jaisa hi — koi login/permission gate nahi.

- **`crypto/index.php`**, **`crypto/encrypt.php`**: `can("manage_keys")`
  ka `if` wrapper hataya — ADD/EDIT buttons ab sabko dikhte hain.
- **`crypto/add.php`**, **`crypto/update.php`**: `requireLogin
  ('manage_keys', true)` hataya. `checkCsrf()` waisa hi hai (session-
  based, login se independent — sab requests par kaam karta hai).
- **`crypto/getKeys.php`**: `$showKeys = can('manage_keys')` wala gate
  poora hataya — ab har response me raw `keys` field hamesha aati hai
  (pehle non-privileged users ke liye `unset($key['keys'])` hota tha).
  Zaroori tha kyunki EDIT modal ko current key value dikhani hoti hai.
- `common/auth.php` ka `AUTH_UNIVERSAL_PERMS` me `manage_keys` waisa hi
  hai (ab bhi likha hai lekin kahin call nahi hota — dead-but-harmless,
  future me gate wapas chahiye ho to already universal-wired hai).

### Verification
- `php -l` clean 5 files. Grep se confirm — `crypto/` folder me koi
  `requireLogin` nahi bacha.
- `users.json` isme touch nahi hua.

### Security note
- `decryptionKey.json` (project → decryption key map) ab poori tarah
  public hai — koi bhi bina login ke keys dekh, add, edit kar sakta
  hai. Ye is turn ke explicit user instruction se hua hai
  ("edit add ka button bhi global hi rahega decrypt and encrypt").

## 2026-09-24 — users.json aur decryptionKey.json git se untrack kiye

User ne pucha ki server par push karne se ye dono live-data files
(real user accounts, real encryption keys) overwrite to nahi ho
jayengi. Check kiya to pata chala **dono git me tracked the**, aur
`decryptionKey.json` me ek uncommitted local test key
(`PREPAID_CARD`, ADD button testing se) bhi tha — matlab agla commit
+ push isko repo me le jaata, aur agla pull/deploy server ka real data
overwrite kar sakta tha.

- **`.gitignore`** (repo root) — naye 2 entries:
  `public/srfAddon/toolHub/common/users.json` aur
  `public/srfAddon/toolHub/crypto/decryptionKey.json`.
- `git rm --cached` dono files par — git index se hata diya, **disk par
  file bilkul waisi hi hai** (test key `PREPAID_CARD` safe hai, kuch
  delete nahi hua). Ab in files me future me kuch bhi badlo, git unhe
  track/commit/push nahi karega.
- **Bonus fix**: `.gitignore` me ek purana unresolved merge-conflict
  (`<<<<<<< HEAD` / `=======` / `>>>>>>> srfAddon_CKYC2.0` markers,
  `testCase/` entry ke around) mila — ye literal text ab tak
  `.gitignore` me pada tha (matlab wo lines gitignore pattern ki tarah
  kaam nahi kar rahi thi, bas junk thi). Dono duplicate `testCase/`
  patterns ko ek `/testCase/` me merge karke conflict markers hata
  diye.

### Verification
- `git status` se confirm: dono json files ab `D` (staged delete from
  index, disk se nahi) dikhate hain; disk par `ls`/`cat` se content
  same confirm kiya.
- Kuch commit ya push nahi kiya — sirf working tree/staging area me
  change hai. User jab chahe commit kare.

### Pending / notes
- Ye sirf is local git repo ka fix hai. Agar server par bhi isi repo
  se `git pull`/deploy hota hai aur wahan pehle se ye files commit history
  me hain, to unko bhi wahan ke `.gitignore` (ya `git update-index
  --assume-unchanged` / server-side ignore) se handle karna padega —
  warna ek purana clone/pull unhe delete kar sakta hai (kyunki humne
  inhe repo se hata diya hai). Is turn me sirf local dev repo touch
  kiya hai, server config change nahi.

## 2026-09-24 — Users "Access" dialog: naya card-based design

User ne ek screenshot diya (polished "Access: shivanshu" modal — icon
avatar, styled Role select, group-card checklist, custom footer
buttons) aur kaha "same to same design kar do".

- **`common/auth.php`**: naya `PERMISSION_GROUPS` const — har group
  (abhi sirf "Axis Dummy Data" aur "Admin") ke liye `icon` + `desc`,
  Users UI card ke liye.
- **`users/userApi.php`**: `list` action ka response me `groups` field
  bhi add kiya (`PERMISSION_GROUPS`).
- **`users/index.php`**: poora Access/Add-User flow SweetAlert2 se
  hata ke ek custom modal (`#accessBackdrop`) me rewrite kiya —
  screenshot se match:
  - Header: round icon avatar, bold title (`Access: {name}` ya
    `Add User`), close (×) button.
  - Add User mode me hi Username/Password fields dikhte hain (edit
    mode me chhupe rehte hain, `#accessCreds`).
  - Role `<select>` icon + chevron ke saath styled dropdown.
  - "Module Access" heading + subtitle.
  - Har group ek tinted card (Axis Dummy Data = green, Admin = amber)
    — icon square, title+description (bordered column), checkboxes
    dayi taraf. Checkbox color `accent-color` se indigo/purple.
  - "Manage Users" checkbox sirf role=admin par dikhta hai (pehle jaisa
    hi rule), warna card me "Not applicable for this role" text.
  - Footer: UPDATE/ADD (indigo, save icon) + Cancel (gray, × icon).
  - Role dropdown change karne par checklist re-render hoti hai naye
    role ke hisaab se, lekin already-ticked checkboxes (jo user ne
    change se pehle tick kiye the) preserve hote hain (`_currentPerm`
    merge fix).
  - Reset Password aur Delete User dialogs waise hi SweetAlert2 par
    hain (screenshot me nahi the, change ki zaroorat nahi thi).

### Verification
- `php -l` clean `common/auth.php`, `users/userApi.php`,
  `users/index.php`; inline `<script>` `node --check` clean.
- Read-only test: `common/auth.php` require karke `PERMISSION_MODULES`
  + `PERMISSION_GROUPS` ka JSON output verify kiya, groups/icons/desc
  sahi structure me hain.
- `users.json` isme touch nahi hua (checksum same).

### Pending / notes
- Real browser me modal open/close, role-switch, save flow ek baar
  click karke dekhna baaki hai.

## 2026-09-24 — Access modal size normal (compact)

User ne kaha card ki width/height/font size sab normal (chhote) karo.

- **`users/index.php`** (`<style>` block): Access modal `max-width`
  620px → 460px, header/body/footer padding aadhe se kam, header icon
  52px → 34px, title 24px → 15.5px. Group card padding 16px → 9px,
  group icon 40px → 28px, group title 15.5px → 12.5px, description
  12.5px → 10.5px, checkbox label 14px → 11.5px, checkbox box 18px →
  14px. Role select/input aur footer buttons bhi isi hisaab se chhote.
- Sirf sizing/spacing change — koi layout/logic touch nahi hua.

### Verification
- `php -l` clean. `users.json` isme touch nahi hua.

## 2026-09-24 — toolHub/.gitignore ka path bug fix

User ne khud `toolHub/.gitignore` bana ke usme
`public/srfAddon/toolHub/common/users.json` aur
`public/srfAddon/toolHub/crypto/decryptionKey.json` daale the (root
`.gitignore` ka pichla fix bhi kisi wajah se reset ho gaya tha, ab wahan
ye 2 entries nahi hain).

- **Bug**: subfolder me rakha `.gitignore` apne andar ke paths **usi
  folder ke relative** resolve karta hai, repo root ke relative nahi.
  `toolHub/.gitignore` me `public/srfAddon/toolHub/...` likhne se git
  `toolHub/public/srfAddon/toolHub/...` dhoondta tha — jo exist hi
  nahi karta, isliye asli files ignore nahi ho rahi thi
  (`git check-ignore` empty/exit 1 deta tha, `git add .` inhe wapas
  track kar leta).
- **Fix (`toolHub/.gitignore`)**: paths ko folder-relative kar diya —
  `common/users.json`, `crypto/decryptionKey.json`.
- Verified: `git check-ignore -v` ab dono files ke liye match dikhata
  hai; `git add --dry-run .` simulate kiya — dono files add nahi hote.
  Disk par dono files ka content pehle jaisa hi hai (checksum verify
  kiya, kuch delete/overwrite nahi hua).

### Pending / notes
- `toolHub/.gitignore` khud abhi tak commit nahi hui (naya untracked
  file) — commit karna user ka decision hai. Root `.gitignore` me ab
  ye 2 entries nahi hain (user ke edit se reset ho gaya), lekin zaroorat
  nahi — `toolHub/.gitignore` akela kaam kar raha hai.

## 2026-09-24 — Password rule: min 3, max koi limit nahi

User ne kaha password create me sirf min length 3 chahiye, upper limit
hatani hai (pehle 3-8 tha).

- **`users/userApi.php`** (`validPassword()`): sirf `< 3` check bacha,
  `> 8` hataya. Message "Password must be at least 3 characters".
- **`common/login.php`** (first-admin setup): waisa hi — `> 8` hataya.
- **`users/index.php`**: Add User ka `accessPassword` input se
  `maxlength="8"` hataya (placeholder "min 3 chars"), JS validation me
  bhi `> 8` hataya. Reset Password SweetAlert dialog se bhi
  `inputAttributes: {maxlength:8}` aur `>8` check hataya.
- Login (existing users, `common/login.php` ke non-setup path) me pehle
  se hi koi length check nahi thi — purane 8+ char / hash wale
  passwords waisa hi chalte rahenge.

### Verification
- `php -l` clean 3 files, inline `<script>` `node --check` clean.
- Grep se confirm — `3-8`/`maxlength=8` ka koi reference kahi nahi
  bacha.
- `users.json` isme touch nahi hua.

## 2026-09-24 — Bug fix: User Management card logout ke baad bhi dikhta tha

User ne repro diya: login → logout → hub par "User Management" card
dikha (galat) → click kiya → login page → apne (non-admin) credentials
se login kiya → "Permission denied" aaya (sahi), saath logout link
(sahi, 403 page ka standard hissa) → logout karne par card hat gaya.
Sawal: card shuru me hi (logged out state me) kyun dikha jab access
nahi hai?

- **Root cause (`index.php`)**: card-hide condition thi
  `if (!$isPublic && $me !== null && !can($t['perm'])) continue;` —
  ye sirf **logged-in-but-no-access** case skip karti thi. Anonymous
  (`$me === null`) ke liye `$me !== null` khud hi false ho jaata,
  isliye `continue` kabhi chalta hi nahi — matlab `adminOnly` card
  (Users) bhi anonymous visitor ko "Login required" badge ke saath
  dikh jaata tha. Pehle (is session ke shuruaati kaam me) `adminOnly`
  cards ka alag rule tha ("hidden unless already has access") jo baad
  ke ek generic refactor me galti se hata gaya tha.
- **Fix**: `adminOnly` cards (abhi sirf Users) ke liye alag branch —
  `if (!can($t['perm'])) continue;` (login-state se independent, bas
  seedha permission check) — matlab card **kabhi nahi dikhta** jab tak
  current session (logged in ya nahi) ke paas `manage_users` na ho.
  Baaki cards (Dummy Data, public tools) ka logic waisa hi hai.

### Verification
- `php -l` clean `index.php`.
- Read-only simulation (5 scenarios): anonymous → hidden; `user` role
  → hidden; `admin` bina `manage_users` → hidden; `admin` +
  `manage_users:true` → visible; `super_admin` → visible. Sab expected
  ke hisaab se.
- `users.json` isme touch nahi hua.
