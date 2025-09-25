<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?show_login=1");
    exit;
}

// Get generated models (simulated data for now)
$models = [
    [
        'id' => 'model_1',
        'car_id' => 12,
        'brand' => 'Proton',
        'model' => 'Saga',
        'year' => 2023,
        'body_type' => 'Sedan',
        'color' => 'White',
        'status' => 'generated',
        'created_at' => '2025-01-17 10:30:00',
        'thumbnail_url' => 'models/thumbnails/model_1.jpg',
        'model_url' => 'models/model_1.glb'
    ],
    [
        'id' => 'model_2',
        'car_id' => 13,
        'brand' => 'Proton',
        'model' => 'Persona',
        'year' => 2022,
        'body_type' => 'Sedan',
        'color' => 'Blue',
        'status' => 'generated',
        'created_at' => '2025-01-17 11:15:00',
        'thumbnail_url' => 'models/thumbnails/model_2.jpg',
        'model_url' => 'models/model_2.glb'
    ],
    [
        'id' => 'model_3',
        'car_id' => 14,
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'body_type' => 'Sedan',
        'color' => 'Red',
        'status' => 'generated',
        'created_at' => '2025-01-17 12:00:00',
        'thumbnail_url' => 'models/thumbnails/model_3.jpg',
        'model_url' => 'models/model_3.glb'
    ]
];

$filter_body_type = $_GET['body_type'] ?? '';
$filter_brand = $_GET['brand'] ?? '';

// Filter models
if ($filter_body_type) {
    $models = array_filter($models, function($model) use ($filter_body_type) {
        return $model['body_type'] === $filter_body_type;
    });
}

if ($filter_brand) {
    $models = array_filter($models, function($model) use ($filter_brand) {
        return $model['brand'] === $filter_brand;
    });
}

// Get unique brands and body types for filters
$brands = array_unique(array_column($models, 'brand'));
$body_types = array_unique(array_column($models, 'body_type'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Model Gallery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .gallery-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .model-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        .model-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .model-thumbnail {
            height: 200px;
            background: linear-gradient(45deg, #f8f9fa 25%, transparent 25%), 
                        linear-gradient(-45deg, #f8f9fa 25%, transparent 25%), 
                        linear-gradient(45deg, transparent 75%, #f8f9fa 75%), 
                        linear-gradient(-45deg, transparent 75%, #f8f9fa 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .model-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .model-card:hover .model-overlay {
            opacity: 1;
        }
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .body-type-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        .model-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Gallery Header -->
                <div class="gallery-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><i class="fas fa-cube me-2"></i>3D Model Gallery</h2>
                            <p class="mb-0">Explore AI-generated 3D vehicle models</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="ai_3d_generator.php" class="btn btn-light btn-lg">
                                <i class="fas fa-plus me-2"></i>Generate New Model
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Model Statistics -->
                <div class="model-stats">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo count($models); ?></div>
                                <div class="stat-label">Total Models</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo count($brands); ?></div>
                                <div class="stat-label">Brands</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo count($body_types); ?></div>
                                <div class="stat-label">Body Types</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-number">100%</div>
                                <div class="stat-label">AI Generated</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <h5><i class="fas fa-filter me-2"></i>Filters</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="brand" class="form-label">Brand</label>
                            <select class="form-select" id="brand" name="brand" onchange="this.form.submit()">
                                <option value="">All Brands</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo htmlspecialchars($brand); ?>" 
                                            <?php echo $filter_brand === $brand ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($brand); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="body_type" class="form-label">Body Type</label>
                            <select class="form-select" id="body_type" name="body_type" onchange="this.form.submit()">
                                <option value="">All Body Types</option>
                                <?php foreach ($body_types as $body_type): ?>
                                    <option value="<?php echo htmlspecialchars($body_type); ?>" 
                                            <?php echo $filter_body_type === $body_type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($body_type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                    <i class="fas fa-times me-1"></i>Clear Filters
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Model Grid -->
                <div class="row">
                    <?php if (empty($models)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center" role="alert">
                                <i class="fas fa-info-circle fa-3x mb-3"></i>
                                <h4>No models found</h4>
                                <p>No 3D models match your current filters.</p>
                                <a href="ai_3d_generator.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Generate Your First Model
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($models as $model): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card model-card">
                                    <div class="model-thumbnail">
                                        <i class="fas fa-cube fa-4x text-primary"></i>
                                        <div class="model-overlay">
                                            <button class="btn btn-light" onclick="viewModel('<?php echo $model['id']; ?>')">
                                                <i class="fas fa-eye me-1"></i>View 3D Model
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php echo htmlspecialchars($model['brand'] . ' ' . $model['model']); ?>
                                            <span class="badge bg-success body-type-badge">
                                                <?php echo htmlspecialchars($model['body_type']); ?>
                                            </span>
                                        </h5>
                                        <p class="card-text">
                                            <strong>Year:</strong> <?php echo $model['year']; ?><br>
                                            <strong>Color:</strong> <?php echo htmlspecialchars($model['color']); ?><br>
                                            <strong>Generated:</strong> <?php echo date('M d, Y', strtotime($model['created_at'])); ?>
                                        </p>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-primary btn-sm" onclick="viewModel('<?php echo $model['id']; ?>')">
                                                <i class="fas fa-eye me-1"></i>View
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="downloadModel('<?php echo $model['id']; ?>')">
                                                <i class="fas fa-download me-1"></i>Download
                                            </button>
                                            <button class="btn btn-outline-info btn-sm" onclick="shareModel('<?php echo $model['id']; ?>')">
                                                <i class="fas fa-share me-1"></i>Share
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 3D Model Viewer Modal -->
    <div class="modal fade" id="modelViewerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">3D Model Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modelViewerContainer" style="height: 600px; background: #f8f9fa; border-radius: 8px;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="fas fa-cube fa-4x text-muted mb-3"></i>
                                <h5>3D Model Viewer</h5>
                                <p class="text-muted">Loading 3D model...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="downloadCurrentModel()">
                        <i class="fas fa-download me-1"></i>Download Model
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentModelId = '';

        function viewModel(modelId) {
            currentModelId = modelId;
            const modal = new bootstrap.Modal(document.getElementById('modelViewerModal'));
            modal.show();
            
            // Simulate loading 3D model
            setTimeout(() => {
                document.getElementById('modelViewerContainer').innerHTML = `
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="text-center">
                            <i class="fas fa-cube fa-4x text-success mb-3"></i>
                            <h5>3D Model Loaded Successfully!</h5>
                            <p class="text-muted">Model ID: ${modelId}</p>
                            <div class="mt-3">
                                <button class="btn btn-outline-primary me-2" onclick="rotateModel()">
                                    <i class="fas fa-sync-alt me-1"></i>Rotate
                                </button>
                                <button class="btn btn-outline-secondary me-2" onclick="zoomModel()">
                                    <i class="fas fa-search-plus me-1"></i>Zoom
                                </button>
                                <button class="btn btn-outline-info" onclick="changeView()">
                                    <i class="fas fa-eye me-1"></i>Change View
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }, 1000);
        }

        function downloadModel(modelId) {
            const link = document.createElement('a');
            link.href = `models/${modelId}.glb`;
            link.download = `${modelId}.glb`;
            link.click();
        }

        function downloadCurrentModel() {
            if (currentModelId) {
                downloadModel(currentModelId);
            }
        }

        function shareModel(modelId) {
            const url = `${window.location.origin}${window.location.pathname}?model=${modelId}`;
            navigator.clipboard.writeText(url).then(() => {
                alert('Model link copied to clipboard!');
            });
        }

        function clearFilters() {
            window.location.href = 'model_gallery.php';
        }

        function rotateModel() {
            alert('Model rotation activated!');
        }

        function zoomModel() {
            alert('Model zoom activated!');
        }

        function changeView() {
            alert('View changed!');
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
