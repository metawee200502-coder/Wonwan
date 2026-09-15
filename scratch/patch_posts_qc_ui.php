<?php
$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

// 1. We need to add premium CSS for the posts tab.
$css = <<<CSS
        /* Premium QC Posts UI */
        .qc-posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        .qc-premium-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(139, 82, 102, 0.08);
            border: 1px solid rgba(139, 82, 102, 0.1);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .qc-premium-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(139, 82, 102, 0.15);
            border-color: rgba(139, 82, 102, 0.3);
        }
        .qc-card-header {
            padding: 20px;
            background: linear-gradient(135deg, #fdf5f7 0%, #fff 100%);
            border-bottom: 1px solid rgba(139, 82, 102, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .qc-card-title {
            font-size: 18px;
            font-weight: 700;
            color: #4a2c36;
            margin: 0 0 6px 0;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .qc-card-author {
            font-size: 13px;
            color: #8b5266;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .qc-card-author i { font-size: 14px; }
        .qc-card-badge {
            background: #ffe4e6;
            color: #e11d48;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 10px rgba(225, 29, 72, 0.2);
        }
        .qc-card-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .qc-specs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            background: #f8fafc;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .qc-spec-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .qc-spec-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        .qc-spec-value {
            font-size: 13px;
            color: #0f172a;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .qc-condition {
            color: #10b981;
        }
        .qc-gallery {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .qc-gallery::-webkit-scrollbar { height: 6px; }
        .qc-gallery::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .qc-img-wrap {
            position: relative;
            flex: 0 0 80px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .qc-img-wrap:hover {
            border-color: #8b5266;
            transform: scale(1.05);
            z-index: 2;
        }
        .qc-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .qc-img-wrap:hover img {
            transform: scale(1.1);
        }
        .qc-img-label {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(0,0,0,0.6);
            color: #fff;
            font-size: 9px;
            text-align: center;
            padding: 4px 0;
            backdrop-filter: blur(4px);
        }
        .qc-card-footer {
            padding: 16px 20px;
            background: #fff;
            border-top: 1px solid rgba(0,0,0,0.05);
            display: flex;
            gap: 12px;
        }
        .qc-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .qc-btn-approve {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .qc-btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        .qc-btn-reject {
            background: #fff;
            color: #e11d48;
            border: 2px solid #e11d48;
        }
        .qc-btn-reject:hover {
            background: #e11d48;
            color: #fff;
            box-shadow: 0 8px 25px rgba(225, 29, 72, 0.3);
            transform: translateY(-2px);
        }
        .qc-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 20px;
            border: 2px dashed #cbd5e1;
            text-align: center;
        }
        .qc-empty-icon {
            font-size: 48px;
            margin-bottom: 20px;
            animation: bounce 2s infinite ease-in-out;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
CSS;

if (strpos($content, '.qc-posts-grid') === false) {
    $content = str_replace('.empty-state {', $css . "\n        .empty-state {", $content);
}

// 2. Replace the old UI
$searchHTML = <<<HTML
            <?php
 if (empty(\$posts)): ?>
                <div class="empty-state">ไม่มีโพสต์รอตรวจสอบ</div>
            <?php
 else: ?>
                <div class="list-container">
                    <?php
 foreach (\$posts as \$post): ?>
                        <div class="qc-card">
                            <div class="qc-card-inner">
                                <div class="qc-info">
                                    <div class="qc-title"><?= htmlspecialchars(\$post['title']) ?> (<?= htmlspecialchars(\$post['author'] ?? 'ไม่ระบุผู้แต่ง') ?>)</div>
                                    <div class="qc-meta">
                                        สภาพ: <?= htmlspecialchars(\$post['condition_percent']) ?>% | ตำหนิ: <?= htmlspecialchars(\$post['defect_types'] ?: 'ไม่มี') ?><br>
                                        เงื่อนไขการรับแลก: <?= htmlspecialchars(\$post['wanted_type']) ?> (<?= htmlspecialchars(\$post['wanted_detail'] ?: '-') ?>)
                                    </div>
                                    <div class="qc-images">
                                        <?php
 foreach (['image_cover', 'image_back', 'image_inside', 'image_edge'] as \$imgF): ?>
                                            <?php
 if (!empty(\$post[\$imgF])): ?>
                                                <img src="<?= htmlspecialchars(\$post[\$imgF]) ?>" onclick="openModal(this.src)" alt="Book Image">
                                            <?php
 endif; ?>
                                        <?php
 endforeach; ?>
                                    </div>
                                </div>
                                <div class="qc-actions">
                                    <form method="POST">
                                        <input type="hidden" name="exchange_id" value="<?= \$post['exchange_id'] ?>">
                                        <button type="submit" name="action" value="approve_post" class="btn-approve" style="width:100%; margin-bottom:10px;">อนุมัติโพสต์</button>
                                        <button type="submit" name="action" value="reject_post" class="btn-reject" style="width:100%;">ไม่อนุมัติ (ลบโพสต์)</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php
 endforeach; ?>
                </div>
            <?php
 endif; ?>
HTML;

$replaceHTML = <<<HTML
            <?php if (empty(\$posts)): ?>
                <div class="qc-empty">
                    <div class="qc-empty-icon">✨</div>
                    <h3 style="color:#334155; margin:0 0 10px 0;">ไม่มีโพสต์รอตรวจสอบ</h3>
                    <p style="color:#64748b; margin:0;">เย้! คุณได้ตรวจสอบโพสต์ทั้งหมดเรียบร้อยแล้ว พักผ่อนได้เลย!</p>
                </div>
            <?php else: ?>
                <div class="qc-posts-grid">
                    <?php foreach (\$posts as \$post): ?>
                        <div class="qc-premium-card">
                            <div class="qc-card-header">
                                <div>
                                    <h3 class="qc-card-title"><?= htmlspecialchars(\$post['title']) ?></h3>
                                    <div class="qc-card-author">
                                        <span>✍️ <?= htmlspecialchars(\$post['author'] ?: 'ไม่ระบุผู้แต่ง') ?></span>
                                    </div>
                                </div>
                                <div class="qc-card-badge">NEW</div>
                            </div>
                            
                            <div class="qc-card-body">
                                <div class="qc-gallery">
                                    <?php 
                                    \$imgLabels = ['image_cover' => 'หน้าปก', 'image_back' => 'ปกหลัง', 'image_inside' => 'ด้านใน', 'image_edge' => 'สันหนังสือ'];
                                    foreach (\$imgLabels as \$imgF => \$label): 
                                    ?>
                                        <?php if (!empty(\$post[\$imgF])): ?>
                                            <div class="qc-img-wrap" onclick="openModal('<?= htmlspecialchars(\$post[\$imgF]) ?>')">
                                                <img src="<?= htmlspecialchars(\$post[\$imgF]) ?>" alt="Book Image">
                                                <div class="qc-img-label"><?= \$label ?></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="qc-specs">
                                    <div class="qc-spec-item">
                                        <span class="qc-spec-label">สภาพหนังสือ</span>
                                        <span class="qc-spec-value qc-condition">⭐ <?= htmlspecialchars(\$post['condition_percent']) ?>%</span>
                                    </div>
                                    <div class="qc-spec-item">
                                        <span class="qc-spec-label">ตำหนิ</span>
                                        <span class="qc-spec-value">🚨 <?= htmlspecialchars(\$post['defect_types'] ?: 'ไม่มี') ?></span>
                                    </div>
                                    <div class="qc-spec-item" style="grid-column: 1 / -1; background: #fff; padding: 10px; border-radius: 8px; border: 1px dashed #cbd5e1;">
                                        <span class="qc-spec-label" style="color:#8b5266;">🎯 เงื่อนไขการแลก</span>
                                        <span class="qc-spec-value" style="color:#4a2c36;"><?= htmlspecialchars(\$post['wanted_type']) ?> <?= \$post['wanted_detail'] ? '('.htmlspecialchars(\$post['wanted_detail']).')' : '' ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="qc-card-footer">
                                <form method="POST" style="display:flex; gap:12px; width:100%;">
                                    <input type="hidden" name="exchange_id" value="<?= \$post['exchange_id'] ?>">
                                    <button type="submit" name="action" value="reject_post" class="qc-btn qc-btn-reject">
                                        <span>✖️ ไม่อนุมัติ</span>
                                    </button>
                                    <button type="submit" name="action" value="approve_post" class="qc-btn qc-btn-approve">
                                        <span>✔️ อนุมัติโพสต์</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
HTML;

$content = str_replace($searchHTML, $replaceHTML, $content);
file_put_contents($file, $content);
echo "Replaced old posts UI with Premium UI\n";
