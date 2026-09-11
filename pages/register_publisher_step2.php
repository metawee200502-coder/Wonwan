<?php
session_start();

// ถ้ายังไม่ผ่านขั้นตอนที่ 1 (ไม่มีข้อมูลใน session) และไม่ได้เพิ่งสมัครสำเร็จ ให้เด้งกลับไปกรอกขั้นตอนที่ 1 ก่อน
if (!isset($_GET['success']) && !isset($_SESSION['publisher_register'])) {
    header('Location: register_publisher.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนสำนักพิมพ์ - วนวรรณ</title>

    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">

    <style>
        /* =========================================
           สไตล์สำหรับหน้า ลงทะเบียนสำนักพิมพ์ (ขั้นตอนที่ 2)
           โทนสี/ตัวแปรเดียวกับ styles.css เพื่อความกลมกลืนกับหน้า reader
           ========================================= */
        .pub-section {
            min-height: 100vh;
            padding: 100px 20px 60px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            box-sizing: border-box;
            background-color: var(--bg-color);

            background-image: url('../assets/image/Background.png');
            background-size: cover;
            background-position: center;
        }

        .pub-card {
            background-color: var(--card-bg);
            padding: 40px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 900px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .pub-title {
            font-size: var(--text-xl);
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 6px;
        }

        .step-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 28px;
        }

        .step-dot {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--text-xs);
            font-weight: 600;
        }

        .step-dot.active {
            background-color: var(--primary-color);
            color: var(--text-light);
        }

        .step-dot.done {
            background-color: var(--accent-green);
            color: var(--text-light);
        }

        .step-line {
            width: 30px;
            height: 2px;
            background-color: var(--border-color);
        }

        .pub-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        @media (max-width: 720px) {
            .pub-form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-col-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 18px;
        }

        .form-col-title svg {
            color: var(--primary-color);
        }

        .input-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-size: var(--text-sm);
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: var(--font-family);
            font-size: var(--text-sm);
            box-sizing: border-box;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--secondary-color);
        }

        /* ----- โซนอัปโหลดเอกสาร ----- */
        .upload-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: var(--text-sm);
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .upload-dropzone {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            background-color: var(--bg-color);
            text-align: center;
            padding: 28px 16px;
            cursor: pointer;
            transition: border-color 0.25s, background-color 0.25s;
            position: relative;
        }

        .upload-dropzone:hover,
        .upload-dropzone.dragover {
            border-color: var(--primary-color);
            background-color: var(--accent-pink-light);
        }

        .upload-dropzone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon-circle {
            width: 44px;
            height: 44px;
            margin: 0 auto 10px;
            border-radius: 50%;
            background-color: var(--accent-pink-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }

        .upload-dropzone-text {
            font-size: var(--text-sm);
            color: var(--text-dark);
            margin: 0;
        }

        .upload-dropzone-text .link-text {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: underline;
        }

        .upload-dropzone-hint {
            font-size: var(--text-xs);
            color: var(--text-muted);
            margin-top: 4px;
        }

        .upload-preview {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background-color: var(--bg-color);
        }

        .upload-preview.show {
            display: flex;
        }

        .upload-preview-name {
            font-size: var(--text-xs);
            color: var(--text-dark);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .upload-preview-remove {
            background: none;
            border: none;
            color: var(--accent-red);
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 0;
        }

        .privacy-note {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: var(--text-xs);
            color: var(--text-muted);
            margin-top: 8px;
        }

        .privacy-note svg {
            color: var(--accent-green);
            flex-shrink: 0;
        }

        .pub-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 30px;
            border-top: 1px solid var(--border-color);
            padding-top: 24px;
        }

        .btn-outline {
            padding: 12px 28px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            color: var(--text-dark);
            font-family: var(--font-family);
            font-size: var(--text-md);
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-outline:hover {
            background-color: var(--accent-pink-light);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--text-light);
            border: none;
            padding: 12px 28px;
            border-radius: 10px;
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color 0.3s;
            font-family: var(--font-family);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .form-error {
            color: var(--accent-red);
            text-align: center;
            margin-bottom: 15px;
            font-size: var(--text-xs);
        }
    </style>
    <link rel="stylesheet" href="../assets/css/auth-layout.css">
</head>
<body>

    <?php include '../components/header.php'; ?>

    <main class="pub-section">
        <div class="pub-card">
            <h1 class="pub-title">ลงทะเบียนสำนักพิมพ์</h1>

            <div class="step-indicator">
                <span class="step-dot done">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </span>
                <span class="step-line"></span>
                <span class="step-dot active">2</span>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <p class="form-error">
                    <?php
                        $errors = [
                            'empty'    => 'กรุณากรอกข้อมูลให้ครบทุกช่อง',
                            'email'    => 'รูปแบบอีเมลสำหรับติดต่อไม่ถูกต้อง',
                            'filetype' => 'เอกสารบริษัท: รองรับเฉพาะไฟล์ PDF, JPG, PNG เท่านั้น',
                            'filesize' => 'เอกสารบริษัท: ขนาดไฟล์ต้องไม่เกิน 10MB',
                            'upload'   => 'เอกสารบริษัท: อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง',
                            'empty_logo'    => 'กรุณาอัปโหลดภาพโลโก้',
                            'filetype_logo' => 'โลโก้: รองรับเฉพาะไฟล์ JPG, PNG เท่านั้น',
                            'filesize_logo' => 'โลโก้: ขนาดไฟล์ต้องไม่เกิน 2MB',
                            'upload_logo'   => 'โลโก้: อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง',
                            'session'  => 'เซสชันหมดอายุ กรุณาเริ่มสมัครใหม่อีกครั้ง',
                            'server'   => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
                        ];
                        $code = $_GET['error'];
                        echo htmlspecialchars($errors[$code] ?? $errors['server']);
                    ?>
                </p>
            <?php endif; ?>

            <?php if (!isset($_GET['success']) || $_GET['success'] != '1'): ?>
            <form action="../process/process_register_publisher.php" method="POST" enctype="multipart/form-data" id="publisherForm">
                <div class="pub-form-grid">

                    <!-- คอลัมน์ซ้าย: ข้อมูลบริษัท -->
                    <div class="form-col">
                        <div class="form-col-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <path d="M3 9h18M9 21V9"></path>
                            </svg>
                            ข้อมูลบริษัท
                        </div>

                        <div class="input-group">
                            <label for="company_name">ชื่อนิติบุคคล/บริษัท</label>
                            <input type="text" id="company_name" name="company_name" placeholder="ระบุชื่อบริษัทตามหนังสือรับรอง" required>
                        </div>

                        <div class="input-group">
                            <span class="upload-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                โลโก้สำนักพิมพ์
                            </span>

                            <label class="upload-dropzone" id="logoDropzone" style="overflow: hidden; padding: 0; display: flex; align-items: center; justify-content: center; min-height: 180px; position: relative;">
                                <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png" required>
                                
                                <div id="logoDropzoneContent" style="padding: 28px 16px; width: 100%; text-align: center;">
                                    <div class="upload-icon-circle">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline>
                                        </svg>
                                    </div>
                                    <p class="upload-dropzone-text">ลากรูปโลโก้มาวางที่นี่ หรือ <span class="link-text">คลิกเพื่อเลือกไฟล์</span></p>
                                    <p class="upload-dropzone-hint">รองรับ JPG, PNG (ไม่เกิน 2MB) • แนะนำขนาด 1:1</p>
                                </div>

                                <img id="logoPreviewImg" src="" alt="Logo Preview" style="width: 100%; height: 100%; object-fit: contain; position: absolute; inset: 0; background-color: var(--bg-color); display: none; z-index: 1;">
                            </label>

                            <div style="text-align: right; margin-top: 6px; min-height: 20px;">
                                <button type="button" id="removeLogoBtn" style="background:none; border:none; color:var(--accent-red); cursor:pointer; font-size:13px; text-decoration:underline; display: none;">ลบรูปโลโก้</button>
                            </div>
                        </div>

                        <div class="input-group">
                            <span class="upload-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                อัปโหลดเอกสารบริษัท
                            </span>

                            <label class="upload-dropzone" id="dropzone" style="padding: 16px;">
                                <input type="file" id="company_document" name="company_document" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="upload-icon-circle" style="width: 36px; height: 36px; margin-bottom: 8px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                </div>
                                <p class="upload-dropzone-text">ลากไฟล์มาวางที่นี่ หรือ <span class="link-text">คลิกเพื่อเลือกไฟล์</span></p>
                                <p class="upload-dropzone-hint">รองรับ PDF, JPG, PNG (ไม่เกิน 10MB) • อายุไม่เกิน 6 เดือน</p>
                            </label>

                            <div class="upload-preview" id="uploadPreview">
                                <span class="upload-preview-name" id="uploadPreviewName" style="cursor:pointer; color:var(--primary-color); text-decoration:underline;" title="คลิกเพื่อดูตัวอย่างเอกสาร">Preview: หนังสือรับรองบริษัท.pdf</span>
                                <button type="button" class="upload-preview-remove" id="uploadPreviewRemove">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- คอลัมน์ขวา: ข้อมูลผู้ติดต่อ -->
                    <div class="form-col">
                        <div class="form-col-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            ข้อมูลผู้ติดต่อ
                        </div>

                        <div class="input-group">
                            <label for="contact_name">ชื่อ-นามสกุล ผู้ประสานงาน</label>
                            <input type="text" id="contact_name" name="contact_name" placeholder="ชื่อจริง และ นามสกุล"
                                   value="<?php echo htmlspecialchars($_SESSION['publisher_register']['fullname'] ?? ''); ?>" required>
                        </div>

                        <div class="input-group">
                            <label for="contact_email">อีเมลสำหรับติดต่อ</label>
                            <input type="email" id="contact_email" name="contact_email" placeholder="example@publisher.com"
                                   value="<?php echo htmlspecialchars($_SESSION['publisher_register']['email'] ?? ''); ?>" required>
                        </div>

                        <div class="input-group">
                            <label for="contact_position">ตำแหน่ง</label>
                            <input type="text" id="contact_position" name="contact_position" placeholder="เช่น ผู้จัดการฝ่ายลิขสิทธิ์" required>
                        </div>

                        <div class="input-group">
                            <label for="contact_phone">เบอร์โทรศัพท์</label>
                            <input type="tel" id="contact_phone" name="contact_phone" placeholder="08x-xxx-xxxx" pattern="[0-9\-]{9,15}" required>
                        </div>

                        <div class="privacy-note">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                            ข้อมูลทั้งหมดจะถูกจัดเก็บตามนโยบายความเป็นส่วนตัว
                        </div>
                    </div>

                </div>

                <div class="pub-actions">
                    <button type="button" class="btn-outline" onclick="window.location.href='register_publisher.php'">ยกเลิก</button>
                    <button type="submit" class="btn-primary">ดำเนินการต่อ</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const dropzone   = document.getElementById('dropzone');
        const fileInput  = document.getElementById('company_document');
        const preview    = document.getElementById('uploadPreview');
        const previewName= document.getElementById('uploadPreviewName');
        const removeBtn  = document.getElementById('uploadPreviewRemove');

        const logoInput = document.getElementById('profile_image');
        const logoPreviewImg = document.getElementById('logoPreviewImg');
        const removeLogoBtn = document.getElementById('removeLogoBtn');
        const logoDropzone = document.getElementById('logoDropzone');
        const logoDropzoneContent = document.getElementById('logoDropzoneContent');

        function handleLogoFile(file) {
            if (!file) return;
            
            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'ประเภทไฟล์ไม่รองรับ',
                    text: 'กรุณาอัปโหลดรูปภาพ JPG หรือ PNG เท่านั้น',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#C97B8B'
                });
                logoInput.value = '';
                return;
            }
            
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'ขนาดไฟล์เกินกำหนด',
                    text: 'กรุณาอัปโหลดรูปภาพโลโก้ที่มีขนาดไม่เกิน 2MB',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#C97B8B'
                });
                logoInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreviewImg.src = e.target.result;
                logoPreviewImg.style.display = 'block';
                logoDropzoneContent.style.visibility = 'hidden';
                removeLogoBtn.style.display = 'inline-block';
            }
            reader.readAsDataURL(file);
        }

        logoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                handleLogoFile(this.files[0]);
            }
        });

        ['dragover', 'dragenter'].forEach(evt => {
            logoDropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                logoDropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(evt => {
            logoDropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                logoDropzone.classList.remove('dragover');
            });
        });

        logoDropzone.addEventListener('drop', function (e) {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                logoInput.files = e.dataTransfer.files;
                handleLogoFile(e.dataTransfer.files[0]);
            }
        });

        removeLogoBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            logoInput.value = '';
            logoPreviewImg.src = '';
            logoPreviewImg.style.display = 'none';
            logoDropzoneContent.style.visibility = 'visible';
            removeLogoBtn.style.display = 'none';
        });

        fileInput.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                previewName.textContent = 'Preview: ' + this.files[0].name;
                preview.classList.add('show');
            }
        });

        ['dragover', 'dragenter'].forEach(evt => {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', function (e) {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                previewName.textContent = 'Preview: ' + e.dataTransfer.files[0].name;
                preview.classList.add('show');
            }
        });

        removeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            fileInput.value = '';
            preview.classList.remove('show');
        });

        // Document Preview Logic
        let currentDocObjectUrl = null;

        window.showDocPreview = function() {
            const fileInput = document.getElementById('company_document');
            const docPreviewModal = document.getElementById('docPreviewModal');
            const docPreviewContent = document.getElementById('docPreviewContent');

            if (fileInput && fileInput.files && fileInput.files[0]) {
                const file = fileInput.files[0];
                if (currentDocObjectUrl) URL.revokeObjectURL(currentDocObjectUrl);
                currentDocObjectUrl = URL.createObjectURL(file);

                docPreviewContent.innerHTML = '';
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = currentDocObjectUrl;
                    img.style.maxWidth = '100%';
                    img.style.maxHeight = '100%';
                    img.style.objectFit = 'contain';
                    img.style.borderRadius = '8px';
                    docPreviewContent.appendChild(img);
                } else if (file.type === 'application/pdf') {
                    const iframe = document.createElement('iframe');
                    iframe.src = currentDocObjectUrl;
                    iframe.style.width = '100%';
                    iframe.style.height = '100%';
                    iframe.style.border = 'none';
                    docPreviewContent.appendChild(iframe);
                } else {
                    docPreviewContent.innerHTML = '<p>ไม่สามารถแสดงตัวอย่างไฟล์ประเภทนี้ได้</p>';
                }
                docPreviewModal.style.display = 'flex';
            }
        };

        window.closeDocPreview = function() {
            const docPreviewModal = document.getElementById('docPreviewModal');
            const docPreviewContent = document.getElementById('docPreviewContent');
            if (docPreviewModal) docPreviewModal.style.display = 'none';
            if (currentDocObjectUrl) {
                URL.revokeObjectURL(currentDocObjectUrl);
                currentDocObjectUrl = null;
            }
            if (docPreviewContent) docPreviewContent.innerHTML = '';
        };

        document.getElementById('uploadPreviewName').addEventListener('click', window.showDocPreview);
    </script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* สไตล์ป๊อปอัปแบบมินิมอลคุมโทนให้เข้ากับฟอร์มสมัครสมาชิกสีชมพู */
        .wanawan-minimal-popup {
            border-radius: 16px !important;
            padding: 2.5rem 2rem !important;
            box-shadow: 0 15px 35px rgba(201, 123, 139, 0.12) !important;
            font-family: 'Prompt', sans-serif !important;
        }
        .wanawan-minimal-title {
            font-size: 20px !important;
            font-weight: 500 !important;
            color: #175564 !important;
            margin-top: 15px !important;
        }
        .wanawan-minimal-text {
            font-size: 14px !important;
            color: #666666 !important;
            font-weight: 300 !important;
            margin-top: 8px !important;
        }
        .wanawan-minimal-btn {
            background-color: #C97B8B !important;
            color: #FFFFFF !important;
            border-radius: 8px !important;
            padding: 10px 30px !important;
            font-size: 14px !important;
            font-weight: 400 !important;
            box-shadow: none !important;
            transition: background-color 0.2s ease;
        }
        .wanawan-minimal-btn:hover {
            background-color: #B5677A !important;
        }
    </style>

    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'รอตรวจสอบ',
                text: 'และจะมีการแจ้งสถานะส่งถึง email',
                confirmButtonText: 'ตกลง',
                iconColor: '#C97B8B',
                background: '#FFFFFF',
                allowOutsideClick: false,
                backdrop: 'rgba(0, 0, 0, 0.15)',
                customClass: {
                    popup: 'wanawan-minimal-popup',
                    title: 'wanawan-minimal-title',
                    htmlContainer: 'wanawan-minimal-text',
                    confirmButton: 'wanawan-minimal-btn'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'login.php';
                }
            });
        });
    </script>
    <?php endif; ?>

    <!-- Document Preview Modal -->
    <div id="docPreviewModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:12px; width:100%; max-width:800px; height:80vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
            <div style="padding:15px 20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; background:var(--bg-color);">
                <h3 style="margin:0; font-size:16px; color:var(--secondary-color); font-weight:600;">ตัวอย่างเอกสาร</h3>
                <button type="button" onclick="closeDocPreview()" style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--text-muted);">&times;</button>
            </div>
            <div style="flex:1; padding:20px; overflow:hidden; display:flex; justify-content:center; align-items:center; background:#f5f5f5;" id="docPreviewContent">
            </div>
        </div>
    </div>
</body>
</html>
