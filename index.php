<?php
// Подключение к PostgreSQL
$db = new PDO('pgsql:host=localhost;dbname=postgres', 'postgres', 'postgres');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Обработка CRUD операций
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $stmt = $db->prepare("DELETE FROM monuments1 WHERE kod = ?");
        $stmt->execute([$_POST['kod']]);
    } elseif (isset($_POST['add'])) {
        $stmt = $db->prepare("INSERT INTO monuments1 (name, type, information, author, address, lat1_d, lon1_d, t_inf, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['type'],
            $_POST['information'],
            $_POST['author'],
            $_POST['address'],
            $_POST['lat1_d'],
            $_POST['lon1_d'],
            $_POST['t_inf'],
            $_POST['foto']
        ]);
    } elseif (isset($_POST['update'])) {
        $stmt = $db->prepare("UPDATE monuments1 SET name=?, type=?, information=?, author=?, address=?, lat1_d=?, lon1_d=?, t_inf=?, foto=? WHERE kod=?");
        $stmt->execute([
            $_POST['name'],
            $_POST['type'],
            $_POST['information'],
            $_POST['author'],
            $_POST['address'],
            $_POST['lat1_d'],
            $_POST['lon1_d'],
            $_POST['t_inf'],
            $_POST['foto'],
            $_POST['kod']
        ]);
    }
}

// Получение всех памятников
$monuments = $db->query("SELECT * FROM monuments1")->fetchAll(PDO::FETCH_ASSOC);

// Получение данных для диаграмм
$typesData = $db->query("SELECT type, COUNT(*) as count FROM monuments1 GROUP BY type")->fetchAll(PDO::FETCH_ASSOC);
$authorsData = $db->query("SELECT author, COUNT(*) as count FROM monuments1 WHERE author IS NOT NULL AND author != '' GROUP BY author ORDER BY count DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);


$filteredMonuments = [];
if (isset($_GET['radius_search'])) {
    $lat = $_GET['lat'];
    $lon = $_GET['lon'];
    $radius = $_GET['radius'];
    
    $allMonuments = $db->query("SELECT * FROM monuments1")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allMonuments as $monument) {
        $distance = calculateDistance($lat, $lon, $monument['lat1_d'], $monument['lon1_d']);
        if ($distance <= $radius) {
            $monument['distance'] = round($distance);
            $filteredMonuments[] = $monument;
        }
    }
    
    usort($filteredMonuments, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
} else {
    $filteredMonuments = $db->query("SELECT * FROM monuments1")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Памятники Ростова-на-Дону</title>
    <meta charset="utf-8">
    <!-- Mapbox -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.14.1/mapbox-gl.js"></script>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-color: #2A2F4F;
        --secondary-color: #917FB3;
        --accent-color: #E5BEEC;
        --background-color: #FDE2F3;
        --text-color: #2A2F4F;
        --success-color: #4CAF50;
        --warning-color: #FFC107;
        --danger-color: #F44336;
        --transition-speed: 0.3s;
    }

    body {
        display: flex;
        margin: 0;
        padding: 0;
        background: var(--background-color);
        color: var(--text-color);
        font-family: 'Segoe UI', system-ui, sans-serif;
        min-height: 100vh;
    }

    #sidebar {
        width: 300px;
        background: var(--primary-color);
        padding: 2rem;
        height: 100vh;
        box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        overflow-y: auto;
        transition: all var(--transition-speed) ease;
        color: white;
    }

    #sidebar:hover {
        box-shadow: 6px 0 25px rgba(0,0,0,0.2);
    }

    #main-content {
        flex: 1;
        padding: 2rem;
        overflow-y: auto;
        background: var(--background-color);
    }

    #map {
        height: 800px;
        width: 100%;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border: 2px solid var(--secondary-color);
    }

    .btn {
        transition: all var(--transition-speed) ease !important;
        padding: 0.8rem 1.5rem !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
    }

    .btn-primary {
        background: var(--secondary-color) !important;
        border: none !important;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(145,127,179,0.4);
    }

    .modal-content {
        border-radius: 15px;
        border: 2px solid var(--accent-color);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }

    .modal-header {
        background: var(--primary-color);
        color: white;
        border-radius: 15px 15px 0 0;
    }

    .form-control {
        border-radius: 8px;
        padding: 0.8rem;
        border: 2px solid var(--accent-color);
        transition: border-color var(--transition-speed);
    }

    .form-control:focus {
        border-color: var(--secondary-color);
        box-shadow: none;
    }

    .legend {
        background: rgba(255,255,255,0.95);
        padding: 1.2rem;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        position: absolute;
        top: 120px;
        right: 30px;
        z-index: 1;
        backdrop-filter: blur(5px);
    }

    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 0.8rem;
        padding: 0.5rem;
        border-radius: 8px;
        background: rgba(255,255,255,0.9);
        transition: transform var(--transition-speed);
    }

    .legend-item:hover {
        transform: translateX(5px);
    }

    .legend-icon {
        margin-right: 1rem;
        font-size: 1.2rem;
        width: 25px;
        text-align: center;
    }

    .chart-container {
        background: white;
        padding: 1.5rem;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        position: relative;
        height: 350px;
    }

    .table-striped>tbody>tr:nth-child(odd)>td {
        background-color: rgba(233,127,179,0.05);
    }

    .table-hover tbody tr:hover td {
        background-color: rgba(233,127,179,0.1);
    }

    .marker-info img {
        border-radius: 10px;
        margin-top: 1rem;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    }

    .custom-marker {
    font-size: 20px;
    transition: all 0.2s ease;
    cursor: pointer;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    }

    .custom-marker:hover {
        font-size: 30px;
        filter: 
            drop-shadow(0 4px 8px rgba(0,0,0,1))
    }

    .custom-marker::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 120%;
        height: 120%;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        z-index: -1;
        opacity: 0;
        transition: opacity 2s ease;
    }

    .custom-marker:hover::after {
        opacity: 1;
    }

    .mapboxgl-marker:not(:hover) {
        transition: none !important;
    }

    .gradient-badge {
        background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.9rem;
    }
    /* Добавляем стили для маршрута */
    .route-info {
        background: rgba(255,255,255,0.95) !important;
        color: #333 !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    .route-step {
        color: #444;
        padding: 5px 0;
        }
        .mapboxgl-ctrl-directions {
            display: none !important;
        }
    .radius-circle {
        pointer-events: none;
        box-shadow: 0 0 0 2px rgba(0,123,255,0.5);
        animation: pulse 2s infinite;
    }
    .mapboxgl-popup-content {
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    max-width: 350px;
}

.marker-info {
    padding: 12px;
    font-size: 14px;
}

.marker-info h4 {
    font-size: 18px;
    font-weight: 600;
    color: #2A2F4F;
    border-bottom: 1px solid #eee;
    padding-bottom: 8px;
    margin-bottom: 12px;
}

.read-more-btn {
    font-size: 13px;
    text-decoration: none;
}



    @media (max-width: 768px) {
        #sidebar {
            width: 100%;
            height: auto;
            position: relative;
        }
        
        #main-content {
            margin-left: 0;
            padding: 1rem;
        }
        
        #map {
            height: 500px;
        }
    }
</style>
</head>
<body>
    <!-- Боковая панель -->
    <div id="sidebar">
        <h4>Управление</h4>
        <hr>
        <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addModal">
            Добавить объект
        </button>
        <button class="btn btn-secondary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#listModal">
            Просмотр объектов
        </button>
        <button class="btn btn-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#chartModal">
            Инфографика
        </button>
        <?php if (isset($_GET['radius_search'])): ?>
        <a href="?" class="btn btn-danger w-100 mb-2" onclick="removeRadiusCircle()">
            <i class="fas fa-times-circle me-2"></i>Сбросить фильтр
        </a>
        <?php else: ?>
            <button class="btn btn-primary w-100 mb-2" id="radiusSearchBtn">
                Поиск в радиусе
            </button>
        <?php endif; ?>
        <button class="btn btn-primary w-100 mb-2" id="routeControl">
            Построение маршрута
        </button>
        <div class="route-info" id="routeInfo" style="display: none;">
            <h6>Текущий маршрут:</h6>
            <div id="routeSteps"></div>
        </div>
    </div>

    <!-- Основное содержимое -->
    <div id="main-content">
        <h1 class="mb-4">Памятники Ростова-на-Дону</h1>
        <div id="map"></div>
        <div class="legend">
            <h6>Легенда:</h6>
            <div class="legend-item"><i class="fas fa-star legend-icon" style="color: red;"></i> Военные памятники</div>
            <div class="legend-item"><i class="fas fa-church legend-icon" style="color: blue;"></i> Храмы</div>
            <div class="legend-item"><i class="fas fa-user legend-icon" style="color: black;"></i> Памятники деятелям</div>
            <div class="legend-item"><i class="fas fa-museum legend-icon" style="color: orange;"></i> Музеи</div>
            <div class="legend-item"><i class="fas fa-archway legend-icon" style="color: purple;"></i> Архитектура</div>
            <div class="legend-item"><i class="fas fa-question-circle legend-icon" style="color: yellow;"></i> Другие типы</div>
        </div>
    </div>

    <!-- Модальное окно добавления объекта -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-lg-custom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel">Добавить новый памятник</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="kod" id="edit-kod">
                        <div class="mb-3">
                            <label for="name" class="form-label">Название</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Тип</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="Военные памятники">Военные памятники</option>
                                <option value="Храмы религиозных конфессий">Храмы</option>
                                <option value="Памятники выдающимся историческим деятелям">Исторические деятели</option>
                                <option value="Памятники выдающимся деятелям литературы и искусства">Деятели искусства</option>
                                <option value="Музеи">Музеи</option>
                                <option value="Памятники архитектуры">Архитектура</option>
                                <option value="Другие">Другие</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="information" class="form-label">Информация</label>
                            <textarea class="form-control" id="information" name="information" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="author" class="form-label">Автор</label>
                            <input type="text" class="form-control" id="author" name="author">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Адрес</label>
                            <input type="text" class="form-control" id="address" name="address">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="lat1_d" class="form-label">Широта</label>
                                <input type="text" class="form-control" id="lat1_d" name="lat1_d" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lon1_d" class="form-label">Долгота</label>
                                <input type="text" class="form-control" id="lon1_d" name="lon1_d" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="t_inf" class="form-label">Доп. информация</label>
                            <input type="text" class="form-control" id="t_inf" name="t_inf">
                        </div>
                        <div class="mb-3">
                            <label for="foto" class="form-label">URL фото</label>
                            <input type="text" class="form-control" id="foto" name="foto">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" name="add" class="btn btn-primary">Добавить</button>
                        <button type="submit" name="update" class="btn btn-success" style="display:none;">Обновить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальное окно списка объектов (обновлено) -->
    <div class="modal fade" id="listModal" tabindex="-1" aria-labelledby="listModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="listModalLabel">Список памятников</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" id="searchInput" class="form-control" placeholder="Поиск по названию или типу...">
                    </div>
                    <?php if (isset($_GET['radius_search'])): ?>
        <div class="alert alert-info mt-3">
            Показаны объекты в радиусе <?= $_GET['radius'] ?> м от выбранной точки
            <a href="?" class="btn btn-sm btn-danger float-end">Сбросить фильтр</a>
        </div>
    <?php endif; ?>
                    <table class="table table-striped" id="monumentsTable">
                        <thead>
                            <tr>
                                <th>Код</th>
                                <th>Название</th>
                                <th>Тип</th>
                                <th>Расстояние</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredMonuments as $monument): ?>
                            <tr>
                                <td><?= $monument['kod'] ?></td>
                                <td><?= htmlspecialchars($monument['name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($monument['type'] ?? '') ?></td>
                                <td><?= isset($monument['distance']) ? $monument['distance'].' м' : '-' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-btn" 
                                            data-kod="<?= $monument['kod'] ?>"
                                            data-name="<?= htmlspecialchars($monument['name'] ?? '') ?>"
                                            data-type="<?= htmlspecialchars($monument['type'] ?? '') ?>"
                                            data-information="<?= htmlspecialchars($monument['information'] ?? '') ?>"
                                            data-author="<?= htmlspecialchars($monument['author'] ?? '') ?>"
                                            data-address="<?= htmlspecialchars($monument['address'] ?? '') ?>"
                                            data-lat1_d="<?= $monument['lat1_d'] ?>"
                                            data-lon1_d="<?= $monument['lon1_d'] ?>"
                                            data-t_inf="<?= htmlspecialchars($monument['t_inf'] ?? '') ?>"
                                            data-foto="<?= htmlspecialchars($monument['foto'] ?? '') ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#addModal">
                                        Редактировать
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="kod" value="<?= $monument['kod'] ?>">
                                        <button type="submit" name="delete" class="btn btn-sm btn-danger">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>




    <!-- Модальное окно инфографики -->
    <div class="modal fade" id="chartModal" tabindex="-1" aria-labelledby="chartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="chartModalLabel">Инфографика</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- 1. Распределение по типам (столбчатая) -->
                        <div class="col-md-6">
                            <div class="chart-title">Распределение по типам (столбчатая)</div>
                            <div class="chart-container">
                                <canvas id="typesBarChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- 2. Распределение по типам (круговая) -->
                        <div class="col-md-6">
                            <div class="chart-title">Распределение по типам (круговая)</div>
                            <div class="chart-container">
                                <canvas id="typesPieChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- 3. Топ авторов -->
                        <div class="col-md-12">
                            <div class="chart-title">Топ 10 авторов памятников</div>
                            <div class="chart-container">
                                <canvas id="authorsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    

    <!-- Bootstrap JS и Turf.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

    <script>
    // Инициализация Mapbox
    mapboxgl.accessToken = 'pk.eyJ1IjoibW9zbXVzZXVtIiwiYSI6ImNrZ295NDM0NjA2b3kzMGw4MWc3ZWI1amcifQ.gPzaXJpxBGq0trqSAmNoPg';
    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/streets-v12',
        center: [39.7139, 47.2367],
        zoom: 12
    });

    // Переменные для управления маршрутом
    let isRouteMode = false;
    let routePoints = [];
    let routeMarkers = [];
    let currentMarkers = [];
    let routeLayer = null;
    const initialMonuments = <?= json_encode($monuments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Функции для работы с маршрутом
    function initRoute() {
        if (map.getLayer('route')) map.removeLayer('route');
        if (map.getSource('route')) map.removeSource('route');
        routePoints = [];
        routeMarkers.forEach(m => m.remove());
        routeMarkers = [];
        document.getElementById('routeSteps').innerHTML = '';
    }

    function createCustomMarker(type, isSelected = false) {
        const el = document.createElement('div');
        el.className = 'custom-marker';
        const iconInfo = getIconForType(type);
        el.innerHTML = `<i class="fas fa-${iconInfo.icon}" 
                          style="color: ${isSelected ? '#ff0000' : iconInfo.color};
                                 text-shadow: 0 1px 3px rgba(0,0,0,0.3)"></i>`;
        return el;
    }

    function getIconForType(type) {
        const typeIcons = {
            'Военные памятники': { icon: 'star', color: 'red' },
            'Храмы религиозных конфессий': { icon: 'church', color: 'blue' },
            'Памятники выдающимся историческим деятелям': { icon: 'user', color: 'black' },
            'Памятники выдающимся деятелям литературы и искусства': { icon: 'user', color: 'black' },
            'Музеи': { icon: 'museum', color: 'orange' },
            'Памятники архитектуры': { icon: 'archway', color: 'purple' }
        };
        return typeIcons[type] || { icon: 'question-circle', color: 'yellow' };
    }

    // Обработчики маршрута
    document.getElementById('routeControl').addEventListener('click', () => {
        if (!isRouteMode) {
            // Начало нового маршрута
            isRouteMode = true;
            initRoute();
            document.getElementById('routeControl').textContent = 'Закончить маршрут';
            document.getElementById('routeInfo').style.display = 'block';
            map.getCanvas().style.cursor = 'pointer';
        } else {
            // Завершение построения маршрута
            isRouteMode = false;
            document.getElementById('routeControl').textContent = 'Построить маршрут';
            document.getElementById('routeInfo').style.display = 'none';
            map.getCanvas().style.cursor = '';
            
            if (routePoints.length >= 2) {
                calculateRoute();
            } else {
                alert('Выберите минимум 2 точки для построения маршрута!');
            }
        }
    });

    // Функция расчета маршрута
    async function calculateRoute() {
        try {
            const coordinates = routePoints
                .map(p => `${p.lng},${p.lat}`)
                .join(';');

            const params = new URLSearchParams({
                geometries: 'geojson',
                overview: 'full',
                steps: 'true',
                access_token: mapboxgl.accessToken
            });

            const response = await fetch(
                `https://api.mapbox.com/directions/v5/mapbox/walking/${coordinates}?${params}`
            );
            
            const data = await response.json();
            
            if (data.routes?.[0]?.geometry) {
                if (map.getSource('route')) map.removeSource('route');
                
                map.addSource('route', {
                    type: 'geojson',
                    data: {
                        type: 'Feature',
                        properties: {},
                        geometry: data.routes[0].geometry
                    }
                });

                map.addLayer({
                    id: 'route',
                    type: 'line',
                    source: 'route',
                    layout: {
                        'line-join': 'round',
                        'line-cap': 'round'
                    },
                    paint: {
                        'line-color': 'red',
                        'line-width': 6,
                        'line-opacity': 0.85
                    }
                });

                // Центрируем карту на маршруте
                const bounds = new mapboxgl.LngLatBounds();
                routePoints.forEach(point => bounds.extend(point));
                map.fitBounds(bounds, { padding: 50 });
            }
        } catch (error) {
            console.error('Ошибка построения маршрута:', error);
            alert('Не удалось построить маршрут между выбранными точками');
        }
    }

    // Добавление маркеров
    function addMarker(monument) {
    const markerElement = createCustomMarker(monument.type);
    
    // Создаем HTML-содержимое для popup с фиксированной высотой и прокруткой
    let popupHTML = `
        <div class="marker-info" style="max-height: 400px; overflow-y: auto; width: 300px; padding: 10px;">
            <h4 class="mb-2">${monument.name}</h4>
            <div class="mb-2">
                Тип: ${monument.type}
            </div>
            <div class="mb-2">
                Автор:  ${monument.author ? `<p> ${monument.author}</p>` : 'Неизвестно'}
            </div>
            <div>
                ${monument.information}
            </div>
            
            Адресс: ${monument.address ? `<p class="mb-1"><i class="fas fa-map-marker-alt me-1"></i> ${monument.address}</p>` : ''}
            
            ${monument.foto ? `
                <img src="${monument.foto}" alt="Фото памятника" 
                     class="img-fluid mt-2 rounded" 
                     style="max-height: 500px; max-width: 100%;"
                     onerror="this.style.display='none'">
            ` : ''}
        </div>
    `;
    
    // Создаем popup с увеличенным максимальным размером
    const popup = new mapboxgl.Popup({
        offset: 25,
        maxWidth: '500px'
    }).setHTML(popupHTML);

    const marker = new mapboxgl.Marker(markerElement)
        .setLngLat([monument.lon1_d, monument.lat1_d])
        .setPopup(popup);

    // Обработчик клика для маршрута (остается без изменений)
    marker.getElement().addEventListener('click', (e) => {
        if (isRouteMode) {
            e.stopPropagation();
            const coords = marker.getLngLat();
            
            if (!routePoints.some(p => 
                p.lat.toFixed(6) === coords.lat.toFixed(6) && 
                p.lng.toFixed(6) === coords.lng.toFixed(6)
            )) {
                routePoints.push(coords);
                
                // Добавляем номер точки
                const numberMarker = document.createElement('div');
                numberMarker.textContent = routePoints.length;
                
                const newMarker = new mapboxgl.Marker(numberMarker)
                    .setLngLat(coords)
                    .addTo(map);
                
                routeMarkers.push(newMarker);
                
                document.getElementById('routeSteps').innerHTML += `
                    <div class="route-step">
                        ${routePoints.length}. ${monument.name}
                        <small>(${coords.lng.toFixed(4)}, ${coords.lat.toFixed(4)})</small>
                    </div>
                `;
            }
        }
    });
    
    marker.addTo(map);
    currentMarkers.push(marker);
}
// Функция расчета расстояния между двумя координатами
<?php
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}?>
    map.on('load', () => {
    initialMonuments.forEach(monument => addMarker(monument));
    
    <?php if(isset($_GET['radius_search'])): ?>
        const center = [<?= $_GET['lon'] ?>, <?= $_GET['lat'] ?>];
        showSearchRadius({lng: center[0], lat: center[1]}, <?= $_GET['radius'] ?>);
    <?php endif; ?>
});

    // Радиусный поиск
    let radiusCircle = null;
    let isSelecting = false;
    const radiusSearchBtn = document.getElementById('radiusSearchBtn');
if (radiusSearchBtn) {
    radiusSearchBtn.addEventListener('click', () => {
        isSelecting = true;
        map.getCanvas().style.cursor = 'crosshair';
        alert('Кликните на карте для выбора центра поиска');
        removeRadiusCircle();
    });
}
// Переменные для круга поиска


// Объявляем переменные для круга поиска
const radiusSourceId = 'radius-source';
const radiusFillLayerId = 'radius-fill-layer';
const radiusStrokeLayerId = 'radius-stroke-layer';

// Функция для отображения круга поиска
function showSearchRadius(center, radius) {
    // Удаляем предыдущие слои
    removeRadiusCircle();
    
    // Создаем круг с помощью Turf.js
    const circle = turf.circle([center.lng, center.lat], radius, {
        steps: 64,
        units: 'meters'
    });
    
    // Добавляем источник данных
    map.addSource(radiusSourceId, {
        "type": "geojson",
        "data": circle
    });
    
    // Добавляем слой заливки
    map.addLayer({
        id: radiusFillLayerId,
        type: 'fill',
        source: radiusSourceId,
        paint: {
            'fill-color': '#007bff',
            'fill-opacity': 0.1
        }
    });
    
    // Добавляем слой контура (пунктирный)
    map.addLayer({
        id: radiusStrokeLayerId,
        type: 'line',
        source: radiusSourceId,
        paint: {
            'line-color': '#007bff',
            'line-width': 2,
            'line-dasharray': [2, 2]
        }
    });
    
}

// Функция для удаления круга
function removeRadiusCircle() {
    if (map.getLayer(radiusFillLayerId)) map.removeLayer(radiusFillLayerId);
    if (map.getLayer(radiusStrokeLayerId)) map.removeLayer(radiusStrokeLayerId);
    if (map.getSource(radiusSourceId)) map.removeSource(radiusSourceId);
}
    map.on('click', async (e) => {
        if (isSelecting) {
            isSelecting = false;
            map.getCanvas().style.cursor = '';
            const radius = prompt('Введите радиус поиска в метрах:', '1000');
            if (radius && !isNaN(radius)) {
                showSearchRadius(e.lngLat, radius);
                const params = new URLSearchParams({
                    radius_search: 1,
                    lat: e.lngLat.lat,
                    lon: e.lngLat.lng,
                    radius: radius
                });
                window.location.href = `?${params.toString()}#listModal`;
            }
        }
    });

    <?php if(isset($_GET['radius_search'])): ?>
        window.addEventListener('load', () => {
            new bootstrap.Modal('#listModal').show();
        });
    <?php endif; ?>
    <?php if(isset($_GET['radius_search'])): ?>
    map.on('load', () => {
        <?php if(isset($_GET['radius_search'])): ?>
        const center = [<?= $_GET['lon'] ?>, <?= $_GET['lat'] ?>];
        showSearchRadius({lng: center[0], lat: center[1]}, <?= $_GET['radius'] ?>);
    <?php endif; ?>
    });
    <?php endif; ?>

    
  

    // Создание элемента для круга
    function createRadiusCircle(radius) {
        const size = Math.min(500, Math.max(50, radius / 3)); // Размер между 50-500px
        const el = document.createElement('div');
        el.className = 'radius-circle';
        el.style.width = size + 'px';
        el.style.height = size + 'px';
        el.style.border = '2px dashed #007bff';
        el.style.borderRadius = '50%';
        el.style.backgroundColor = 'rgba(0, 123, 255, 0.1)';
        el.style.position = 'absolute';
        el.style.transform = 'translate(-50%, -50%)';
        el.style.zIndex = '9';
        return el;
    }

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit-kod').value = this.dataset.kod;
            document.getElementById('name').value = this.dataset.name;
            document.getElementById('type').value = this.dataset.type;
            document.getElementById('information').value = this.dataset.information;
            document.getElementById('author').value = this.dataset.author;
            document.getElementById('address').value = this.dataset.address;
            document.getElementById('lat1_d').value = this.dataset.lat1_d;
            document.getElementById('lon1_d').value = this.dataset.lon1_d;
            document.getElementById('t_inf').value = this.dataset.t_inf;
            document.getElementById('foto').value = this.dataset.foto;
            
            document.querySelector('[name="add"]').style.display = 'none';
            document.querySelector('[name="update"]').style.display = 'inline-block';
        });
    });

    document.getElementById('searchInput').addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        document.querySelectorAll('#monumentsTable tbody tr').forEach(row => {
            const name = row.cells[1].textContent.toLowerCase();
            const type = row.cells[2].textContent.toLowerCase();
            row.style.display = (name.includes(searchText) || type.includes(searchText)) ? '' : 'none';
        });
    });

        // Инициализация диаграмм
        const chartModal = document.getElementById('chartModal');
        let charts = [];

        chartModal.addEventListener('shown.bs.modal', function() {
            initCharts();
        });

        chartModal.addEventListener('hidden.bs.modal', function() {
            destroyCharts();
        });

        function initCharts() {
            // 1. Распределение по типам (столбчатая)
            const typesBarCtx = document.getElementById('typesBarChart').getContext('2d');
            charts.push(new Chart(typesBarCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(',', array_map(function($item) { return "'".addslashes($item['type'])."'"; }, $typesData)); ?>],
                    datasets: [{
                        label: 'Количество объектов',
                        data: [<?php echo implode(',', array_column($typesData, 'count')); ?>],
                        backgroundColor: '#36A2EB',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            }));

            // 2. Распределение по типам (круговая)
            const typesPieCtx = document.getElementById('typesPieChart').getContext('2d');
            charts.push(new Chart(typesPieCtx, {
                type: 'pie',
                data: {
                    labels: [<?php echo implode(',', array_map(function($item) { return "'".addslashes($item['type'])."'"; }, $typesData)); ?>],
                    datasets: [{
                        data: [<?php echo implode(',', array_column($typesData, 'count')); ?>],
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                            '#FF9F40', '#8AC24A', '#F06292', '#7986CB', '#A1887F'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            }));

            // 3. Топ авторов
            const authorsCtx = document.getElementById('authorsChart').getContext('2d');
            charts.push(new Chart(authorsCtx, {
                type: 'bar',
                data: {
                    labels: [<?php 
                        $authorsLabels = [];
                        foreach ($authorsData as $item) {
                            $authorsLabels[] = "'".addslashes($item['author'] ?: 'Неизвестный автор')."'";
                        }
                        echo implode(',', $authorsLabels);
                    ?>],
                    datasets: [{
                        label: 'Количество работ',
                        data: [<?php echo implode(',', array_column($authorsData, 'count')); ?>],
                        backgroundColor: '#4BC0C0',
                        barThickness: 'flex',
                        maxBarThickness: 50
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // Горизонтальные столбцы
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            }));
        }

        function destroyCharts() {
            charts.forEach(chart => chart.destroy());
            charts = [];
        }
    </script>
</body>
</html>