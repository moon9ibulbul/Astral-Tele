1. **Fix Popular Comic UI on the index page.**
   - In `js/app.js`, change the grid template of the popular posts to exactly match the main comic grid template (vertical layout: thumbnail on top, title below).
   - Remove `flex`, change `w-2/5 aspect-[16/9]` to `aspect-[3/4] bg-gray-200 relative`, and make the thumbnail container take full width. Move the title under the thumbnail container into a `p-3 flex-1 flex flex-col` div.

2. **Secure PDF Files via Proxy.**
   - The user requests: "Amankan file PDF agar tidak bisa dibuka diluar. Mungkin dengan cara memberi password ke PDFnya sebelum diupload ke S3. Nanti buat reader agar bisa membaca password tersebut. Passwordnya dimasukkan oleh admin saat mau mengunggah, jadi setiap file bisa berbeda-beda. Mungkin manfaatkan database? Jangan sampai password bisa disniff."
   - **Upload**: In `api/chapters.php`, when the admin uploads a PDF and specifies a password, encrypt the file using PHP's `openssl_encrypt('aes-256-cbc')` with a key derived from the password via `hash('sha256', $password, true)`. Prepend the random IV to the ciphertext. The encrypted file is uploaded to S3.
   - **Proxy**: Create a new file `api/pdf_proxy.php`.
   - `api/pdf_proxy.php` will:
     1. Verify the user is authenticated and has unlocked the chapter (using `auth.php` and `unlocked_chapters` check).
     2. Retrieve the `password` and `pdf_url` from the database.
     3. Fetch the encrypted PDF from S3.
     4. Extract the IV, then decrypt it in memory using `openssl_decrypt` with the database password.
     5. Serve the decrypted PDF bytes with `Content-Type: application/pdf`.
   - Update `api/chapters.php` so that if `needsUnlock == false` and the chapter has a password, it sets `pdf_url = '/api/pdf_proxy.php?id=' . $chapterId`.

3. **Verify Proxy Changes**
   - Apply the changes to `api/chapters.php` and create `api/pdf_proxy.php`.
   - Ensure the local PHP server runs and test the proxy using a test PHP script with `curl` to verify `api/pdf_proxy.php` decrypts the file accurately and `api/chapters.php` uploads and encrypts it accurately.

4. **UI Polish.**
   - Change `quest.html` body padding class from `pb-20` to `pb-24` to ensure bottom nav spacing is consistent across pages.
   - Change `admin.html` body class to include `pb-24` as well.

5. **Run all relevant tests.**
   - Start the local PHP development server using `php -S localhost:8000 &`. Use `run_in_bash_session` to run a basic `curl` against the index page to ensure no syntax errors were introduced.
   - Run tests to ensure the changes are correct and have not introduced regressions.

6. **Pre-commit Steps.**
   - Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.
