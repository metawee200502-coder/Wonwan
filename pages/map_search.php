<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$breadcrumbs = [
    ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'],
    ['label' => 'ค้นหาบนแผนที่', 'url' => ''],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ค้นหาบนแผนที่ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <!-- MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

    <style>
        body {
            overflow: hidden; /* Prevent page scroll for native app feel */
        }
        .map-page-wrapper {
            position: relative;
            height: calc(100vh - 105px); /* Header + Breadcrumb approx height */
            width: 100%;
            display: flex;
            flex-direction: row; /* Change to row to accommodate sidebar */
        }
        
        /* Sidebar Styles */
        .map-sidebar {
            width: 380px;
            background: #fff;
            box-shadow: 2px 0 12px rgba(0,0,0,0.15);
            z-index: 1001; /* Above map */
            display: flex;
            flex-direction: column;
            transform: translateX(-100%);
            transition: transform 0.3s ease, opacity 0.3s ease;
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            opacity: 0;
            pointer-events: none;
        }
        .map-sidebar.active {
            transform: translateX(0);
            opacity: 1;
            pointer-events: auto;
        }
        .sidebar-header {
            padding: 18px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fffaf7;
        }
        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary-color);
        }
        .sidebar-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            padding: 4px;
            display: flex;
            align-items: center;
            transition: color 0.2s;
        }
        .sidebar-close:hover { color: var(--primary-color); }
        .sidebar-content {
            padding: 24px 20px;
            flex: 1;
            overflow-y: auto;
        }
        .sidebar-img-wrapper {
            width: 100%;
            height: 240px;
            background: linear-gradient(145deg, #f5e3e5, #fff7f2);
            border: 1px solid #ead4d8;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .sidebar-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
            line-height: 1.35;
        }
        .sidebar-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 12px;
        }
        .sidebar-distance {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 24px;
        }
        .sidebar-action {
            display: block;
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: #fff;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            font-family: var(--font-family);
            font-weight: 600;
            font-size: 15px;
            transition: background 0.2s, transform 0.1s;
        }
        .sidebar-action:hover { background: var(--primary-hover); transform: translateY(-1px); }
        
        .sidebar-action:hover { background: var(--primary-hover); transform: translateY(-1px); }
        
        /* List Items in Sidebar (for Clusters) */
        .sidebar-list-item {
            display: flex;
            gap: 14px;
            padding: 14px 10px;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: var(--text-dark);
            transition: background 0.2s;
            border-radius: 8px;
        }
        .sidebar-list-item:hover { background: #fcf8f5; }
        .sidebar-list-item:last-child { border-bottom: none; }
        .sidebar-list-img {
            width: 65px;
            height: 90px;
            flex-shrink: 0;
            border-radius: 6px;
            overflow: hidden;
            background: #f7f3f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ead4d8;
        }
        .sidebar-list-img img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Cover for lists to look uniform */
        }
        .sidebar-list-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .sidebar-list-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 6px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .sidebar-list-price {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 6px;
        }
        .sidebar-list-distance {
            font-size: 12px;
            color: var(--text-muted);
        }

        #map {
            flex: 1;
            width: 100%;
            z-index: 1;
        }

        .map-controls {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: #fff;
            border-radius: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            padding: 6px;
            gap: 4px;
        }

        .map-toggle-btn {
            border: none;
            background: transparent;
            padding: 10px 24px;
            font-family: var(--font-family);
            font-size: 15px;
            font-weight: 600;
            color: var(--text-muted);
            border-radius: 24px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .map-toggle-btn:hover {
            color: var(--primary-color);
        }

        .map-toggle-btn.active {
            background: var(--primary-color);
            color: #fff;
            box-shadow: 0 2px 8px rgba(211, 148, 139, 0.4);
        }

        /* Custom Markers */
        .custom-book-marker {
            background: #fff;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
            padding: 3px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.2s;
        }
        .custom-book-marker:hover {
            transform: scale(1.05) translateY(-5px);
            z-index: 1000 !important;
        }
        .custom-book-marker img {
            width: 44px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        .custom-book-marker .price {
            font-size: 11px;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 2px;
            white-space: nowrap;
        }

        .custom-shop-marker {
            background: #fff;
            padding: 4px;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            width: 50px;
            height: 65px;
            position: relative;
            transition: transform 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .custom-shop-marker:hover {
            transform: scale(1.1);
            z-index: 1000 !important;
        }
        .custom-shop-marker img {
            width: 100%;
            height: 42px;
            object-fit: cover;
            border-radius: 4px;
            background: #f0f0f0;
        }
        .custom-shop-marker .name-tag {
            font-size: 9px;
            font-weight: 600;
            color: var(--primary-color);
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            text-align: center;
        }
        
        /* Adjusted map controls position to avoid sidebar overlap if needed, but since it's centered it should be fine. We can shift it right slightly if sidebar is open, but absolute centering is okay */


    </style>
</head>
<body>

<?php require __DIR__ . '/../components/header.php'; ?>

<main class="map-page-wrapper">
    <!-- Sidebar -->
    <aside id="mapSidebar" class="map-sidebar">
        <div class="sidebar-header">
            <div style="display:flex;align-items:center;gap:8px;">
                <button class="sidebar-back" id="backSidebar" aria-label="ย้อนกลับ" title="ย้อนกลับ" style="display:none;background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;transition:color 0.2s;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <h2 id="sidebarTitle">รายละเอียด</h2>
            </div>
            <button class="sidebar-close" id="closeSidebar" aria-label="ปิด" title="ปิด">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div id="sidebarContent" class="sidebar-content">
            <!-- Content injected via JS -->
        </div>
    </aside>

    <!-- Controls -->
    <div class="map-controls">
        <button class="map-toggle-btn active" data-type="books">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            หนังสือที่ลงขาย/แลกเปลี่ยน
        </button>
        <button class="map-toggle-btn" data-type="shops">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            ร้านหนังสือและนักอ่าน
        </button>
    </div>

    <!-- Map Container -->
    <div id="map"></div>
</main>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<!-- MarkerCluster JS -->
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const map = L.map('map').setView([14.9799, 102.0978], 6); // Default view (Korat approx, Thailand center)

        // Using standard OpenStreetMap tiles (free, no API key required)
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        let markersGroup = L.markerClusterGroup({
            maxClusterRadius: 40,
            spiderfyOnMaxZoom: true,
            spiderfyDistanceMultiplier: 3.2,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true
        });
        
        window.openMarkerFromList = function(id) {
            let layer = markersGroup.getLayer(id);
            if (layer) {
                layer.fire('click', { fromList: true });
            }
        };
        
        // Handle clicking on a cluster
        markersGroup.on('clusterclick', function (a) {
            sidebarHistory = []; // Reset history
            backSidebar.style.display = 'none';
            
            let markers = a.layer.getAllChildMarkers();
            let html = '';
            
            sidebarTitle.innerText = `พบ ${markers.length} รายการในบริเวณนี้`;
            
            markers.forEach(m => {
                let d = m.itemData;
                if (!d) return;
                
                let title = d.type === 'books' ? d.item.title : d.item.name;
                let priceOrFollowers = d.type === 'books' ? d.priceText : `<span style="color:var(--text-muted);font-weight:normal;font-size:13px">ผู้ติดตาม ${parseInt(d.item.followers_count).toLocaleString()} คน</span>`;
                
                let extraInfo = '';
                if (d.type === 'books') {
                    let condition = d.item.condition_level === 'new' ? 'มือ 1' : (d.item.condition_level === 'exchange' ? 'แลกเปลี่ยน' : 'มือ 2');
                    let seller = d.item.seller_name || 'ไม่ทราบชื่อ';
                    extraInfo = `<div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">สภาพ: <span style="color:#444;font-weight:500">${condition}</span> &nbsp;•&nbsp; ผู้ขาย: <span style="color:#444;font-weight:500">${seller}</span></div>`;
                }
                
                html += `
                    <div class="sidebar-list-item" style="cursor:pointer" onclick="openMarkerFromList(${m._leaflet_id})">
                        <div class="sidebar-list-img">
                            ${d.fallback}
                        </div>
                        <div class="sidebar-list-info">
                            <div class="sidebar-list-title">${title}</div>
                            ${extraInfo}
                            <div class="sidebar-list-price">${priceOrFollowers}</div>
                            <div class="sidebar-list-distance">${d.distanceText}</div>
                        </div>
                    </div>
                `;
            });
            
            sidebarContent.innerHTML = html;
            mapSidebar.classList.add('active');
        });

        map.addLayer(markersGroup);

        // Sidebar logic
        const mapSidebar = document.getElementById('mapSidebar');
        const sidebarContent = document.getElementById('sidebarContent');
        const sidebarTitle = document.getElementById('sidebarTitle');
        const closeSidebar = document.getElementById('closeSidebar');
        const backSidebar = document.getElementById('backSidebar');
        
        let sidebarHistory = [];
        
        function updateSidebar(title, html, pushHistory = true, forceBackBtn = null) {
            if (pushHistory) {
                sidebarHistory.push({
                    title: sidebarTitle.innerText,
                    html: sidebarContent.innerHTML
                });
            }
            sidebarTitle.innerText = title;
            sidebarContent.innerHTML = html;
            
            if (forceBackBtn !== null) {
                backSidebar.style.display = forceBackBtn ? 'block' : 'none';
            } else {
                backSidebar.style.display = sidebarHistory.length > 0 ? 'block' : 'none';
            }
            
            mapSidebar.classList.add('active');
        }
        
        backSidebar.addEventListener('click', () => {
            if (sidebarHistory.length > 0) {
                let prev = sidebarHistory.pop();
                sidebarTitle.innerText = prev.title;
                sidebarContent.innerHTML = prev.html;
                backSidebar.style.display = sidebarHistory.length > 0 ? 'block' : 'none';
            } else {
                mapSidebar.classList.remove('active');
                backSidebar.style.display = 'none';
            }
        });

        closeSidebar.addEventListener('click', () => {
            mapSidebar.classList.remove('active');
            sidebarHistory = [];
            backSidebar.style.display = 'none';
        });

        map.on('click', () => {
            mapSidebar.classList.remove('active');
            sidebarHistory = [];
            backSidebar.style.display = 'none';
        });

        // Add GPS Locate Button
        L.Control.Locate = L.Control.extend({
            options: { position: 'bottomright' },
            onAdd: function (map) {
                var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
                container.style.backgroundColor = 'white';
                container.style.width = '40px';
                container.style.height = '40px';
                container.style.display = 'grid';
                container.style.placeItems = 'center';
                container.style.cursor = 'pointer';
                container.style.borderRadius = '8px';
                container.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
                container.title = 'ตำแหน่งของฉัน';
                
                container.innerHTML = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--primary-color)"><path d="M12 2a10 10 0 1 0 10 10"></path><circle cx="12" cy="12" r="3"></circle><line x1="12" y1="2" x2="12" y2="4"></line><line x1="12" y1="20" x2="12" y2="22"></line><line x1="20" y1="12" x2="22" y2="12"></line><line x1="2" y1="12" x2="4" y2="12"></line></svg>`;
                
                container.onclick = function(e){
                    e.stopPropagation();
                    if(userLocation) {
                        map.setView(userLocation, 14);
                    } else {
                        alert("กำลังค้นหาตำแหน่งของคุณ หรือคุณอาจยังไม่อนุญาตการเข้าถึง GPS");
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition((pos) => {
                                userLocation = [pos.coords.latitude, pos.coords.longitude];
                                map.setView(userLocation, 14);
                            });
                        }
                    }
                }
                return container;
            }
        });
        map.addControl(new L.Control.Locate());

        function getImageUrl(img, type = 'book') {
            if (!img) return '';
            if (img.startsWith('http')) return img;
            let val = img.replace(/\\/g, '/').replace(/^\//, '');
            
            // Derive project root from the current path
            let projectRoot = window.location.pathname.replace(/\/pages\/map_search\.php$/, '');
            if (projectRoot === window.location.pathname) projectRoot = ''; // Fallback if URL doesn't match expected pattern
            
            if (type === 'book') {
                return val.startsWith('uploads/') ? projectRoot + '/pages/' + val : projectRoot + '/assets/image/books/' + val;
            } else {
                return val.startsWith('uploads/') ? projectRoot + '/pages/' + val : projectRoot + '/assets/image/profiles/' + val;
            }
        }

        let userLocation = null;
        let currentType = 'books';

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((pos) => {
                userLocation = [pos.coords.latitude, pos.coords.longitude];
                
                L.circleMarker(userLocation, {
                    radius: 8,
                    fillColor: '#4285F4',
                    color: '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 1
                }).bindPopup("<b>ตำแหน่งของคุณ</b>").addTo(map);
                
                // Only center if we are starting fresh
                if (map.getZoom() === 6) {
                    map.setView(userLocation, 12);
                }
                
                // Re-load markers now that we have user location to show distances
                loadMarkers(currentType);
            }, () => {
                console.log("Geolocation denied or failed.");
            });
        }

        async function loadMarkers(type) {
            try {
                const res = await fetch('/api/map_data.php?type=' + type);
                const json = await res.json();
                
                if (json.status === 'success' && json.data) {
                    markersGroup.clearLayers();
                    let bounds = L.latLngBounds();
                    
                    json.data.forEach(item => {
                        let lat = parseFloat(item.latitude);
                        let lng = parseFloat(item.longitude);
                        if (isNaN(lat) || isNaN(lng)) return;
                        
                        let latLng = L.latLng(lat, lng);
                        bounds.extend(latLng);

                        // Define projectRoot
                        let projectRoot = window.location.pathname.replace(/\/pages\/map_search\.php$/, '');
                        if (projectRoot === window.location.pathname) projectRoot = '';

                        let distanceHtml = '';
                        if (userLocation) {
                            let dist = map.distance(userLocation, [lat, lng]);
                            let dText = dist < 1000 ? Math.round(dist) + ' ม.' : (dist/1000).toFixed(1) + ' กม.';
                            distanceHtml = `<div style="font-size:12px;color:#888;margin-top:4px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ห่าง ${dText}</div>`;
                        }

                        if (type === 'books') {
                            let imgUrl = getImageUrl(item.image_cover, 'book');
                            let priceText = item.price > 0 ? '฿' + parseFloat(item.price).toLocaleString() : 'แลกเปลี่ยน';
                            let fallback = imgUrl ? `<img src="${imgUrl}" alt="">` : `<div style="width:44px;height:60px;background:#f3e8e6;display:grid;place-items:center;font-size:10px;border-radius:4px">ไม่มีภาพ</div>`;
                            
                            let icon = L.divIcon({
                                className: 'custom-div-icon',
                                html: `<div class="custom-book-marker">
                                        ${fallback}
                                        <div class="price">${priceText}</div>
                                       </div>`,
                                iconSize: [54, 84],
                                iconAnchor: [27, 84]
                            });

                            let marker = L.marker([lat, lng], {icon: icon});
                            
                            let distanceText = '';
                            if (userLocation) {
                                let dist = map.distance(userLocation, [lat, lng]);
                                if (dist < 1000) {
                                    distanceText = `<div style="font-size:12px;color:#888;margin-top:4px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ห่าง ${Math.round(dist)} ม.</div>`;
                                } else {
                                    distanceText = `<div style="font-size:12px;color:#888;margin-top:4px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ห่าง ${(dist/1000).toFixed(1)} กม.</div>`;
                                }
                            }

                            let projectRoot = window.location.pathname.replace(/\/pages\/map_search\.php$/, '');
                            if (projectRoot === window.location.pathname) projectRoot = '';
                            
                            let detailUrl = projectRoot + `/pages/book_detail.php?id=${item.book_id}`;
                            let condition = item.condition_level === 'new' ? 'มือ 1' : (item.condition_level === 'exchange' ? 'แลกเปลี่ยน' : 'มือ 2');
                            let seller = item.seller_name || 'ไม่ทราบชื่อ';
                            
                            // Attach data for cluster click
                            marker.itemData = {
                                type: 'books',
                                item: item,
                                fallback: fallback,
                                priceText: priceText,
                                distanceText: distanceText,
                                detailUrl: detailUrl
                            };
                            
                            marker.on('click', function(e) {
                                if (e && e.originalEvent) {
                                    L.DomEvent.stopPropagation(e.originalEvent);
                                }
                                let fromList = !!(e && e.fromList);
                                if (!fromList) {
                                    sidebarHistory = [];
                                }
                                
                                let html = `
                                    <div class="sidebar-img-wrapper">
                                        ${fallback}
                                    </div>
                                    <div class="sidebar-title">${item.title}</div>
                                    <div style="font-size:14.5px;color:var(--text-muted);margin-bottom:8px">สภาพ: <span style="color:var(--text-dark);font-weight:500">${condition}</span> &nbsp;•&nbsp; ผู้ขาย: <span style="color:var(--text-dark);font-weight:500">${seller}</span></div>
                                    <div class="sidebar-price">${priceText}</div>
                                    <div class="sidebar-distance">${distanceText}</div>
                                    <a href="${detailUrl}" class="sidebar-action">ดูรายละเอียดเพิ่มเติม</a>
                                `;
                                updateSidebar('ข้อมูลหนังสือ', html, fromList, true);
                            });
                            
                            markersGroup.addLayer(marker);

                        } else if (type === 'shops') {
                            let imgUrl = getImageUrl(item.profile_image, 'shop');
                            let nameChar = item.name ? item.name.substring(0, 1) : '?';
                            let fallback = imgUrl ? `<img src="${imgUrl}" alt="" onerror="this.outerHTML='<div style=\\\'width:100%;height:42px;background:#f0f0f0;border-radius:4px;display:grid;place-items:center;font-size:20px;color:#999\\\'>!</div>'">` : `<div style="width:100%;height:42px;background:#f7dfe8;border-radius:4px;display:grid;place-items:center;font-size:24px;color:var(--primary-color)">${nameChar}</div>`;
                            
                            let icon = L.divIcon({
                                className: 'custom-div-icon',
                                html: `<div class="custom-shop-marker">${fallback}<div class="name-tag">${item.name}</div></div>`,
                                iconSize: [50, 65],
                                iconAnchor: [25, 65]
                            });

                            let marker = L.marker([lat, lng], {icon: icon});
                            
                            let distanceText = '';
                            if (userLocation) {
                                let dist = map.distance(userLocation, [lat, lng]);
                                if (dist < 1000) {
                                    distanceText = `<div style="font-size:12px;color:#888;margin-top:2px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ห่าง ${Math.round(dist)} ม.</div>`;
                                } else {
                                    distanceText = `<div style="font-size:12px;color:#888;margin-top:2px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ห่าง ${(dist/1000).toFixed(1)} กม.</div>`;
                                }
                            }

                            let projectRoot = window.location.pathname.replace(/\/pages\/map_search\.php$/, '');
                            if (projectRoot === window.location.pathname) projectRoot = '';
                            
                            let detailUrl = projectRoot + `/pages/profile.php?id=${item.id}&type=${item.partner_type}`;
                            
                            // Attach data for cluster click
                            marker.itemData = {
                                type: 'shops',
                                item: item,
                                fallback: fallback,
                                priceText: '', // not used for shop in cluster
                                distanceText: distanceText,
                                detailUrl: detailUrl
                            };
                            
                            marker.on('click', function(e) {
                                if (e && e.originalEvent) {
                                    L.DomEvent.stopPropagation(e.originalEvent);
                                }
                                let fromList = !!(e && e.fromList);
                                if (!fromList) {
                                    sidebarHistory = [];
                                }
                                
                                let html = `
                                    <div class="sidebar-img-wrapper">
                                        ${fallback}
                                    </div>
                                    <div class="sidebar-title">${item.name}</div>
                                    <div style="color:var(--text-muted); margin-bottom: 12px; font-size: 14px;">มีผู้ติดตาม ${parseInt(item.followers_count).toLocaleString()} คน</div>
                                    <div class="sidebar-distance">${distanceText}</div>
                                    <a href="${detailUrl}" class="sidebar-action">ดูโปรไฟล์ร้านค้า</a>
                                `;
                                updateSidebar('ข้อมูลร้านค้า/นักอ่าน', html, fromList, true);
                            });

                            markersGroup.addLayer(marker);
                        }
                    });
                    
                    if (markersGroup.getLayers().length > 0 && userLocation === null && map.getZoom() === 6) {
                         map.fitBounds(bounds, {padding: [50, 50]});
                    }
                }
            } catch (err) {
                console.error("Error loading map data:", err);
            }
        }

        const toggleBtns = document.querySelectorAll('.map-toggle-btn');
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                toggleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentType = this.dataset.type;
                loadMarkers(currentType);
            });
        });

        loadMarkers('books');
    });
</script>

</body>
</html>
