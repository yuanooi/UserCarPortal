<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?show_login=1");
    exit;
}

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$success = false;
$generated_model = null;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_model') {
        $car_id = intval($_POST['car_id'] ?? 0);
        $body_type = $_POST['body_type'] ?? '';
        $brand = $_POST['brand'] ?? '';
        $model = $_POST['model'] ?? '';
        $year = intval($_POST['year'] ?? 0);
        $color = $_POST['color'] ?? '';
        $custom_prompt = trim($_POST['custom_prompt'] ?? '');

        if ($car_id <= 0 || empty($body_type) || empty($brand) || empty($model)) {
            $message = "⚠️ Please fill in all required fields";
        } else {
            // Generate AI prompt based on vehicle specifications
            $ai_prompt = generateAIPrompt($body_type, $brand, $model, $year, $color, $custom_prompt);
            
            // Simulate AI model generation (in real implementation, this would call an AI service)
            $model_data = generate3DModel($ai_prompt, $car_id);
            
            if ($model_data) {
                $message = "✅ 3D model generated successfully!";
                $success = true;
                $generated_model = $model_data;
            } else {
                $message = "❌ Failed to generate 3D model";
            }
        }
    }
}

// Function to generate AI prompt
function generateAIPrompt($body_type, $brand, $model, $year, $color, $custom_prompt) {
    $prompt = "Generate a detailed 3D vehicle model for: ";
    $prompt .= "Brand: $brand, ";
    $prompt .= "Model: $model, ";
    $prompt .= "Year: $year, ";
    $prompt .= "Body Type: $body_type, ";
    $prompt .= "Color: $color";
    
    if (!empty($custom_prompt)) {
        $prompt .= ". Additional requirements: $custom_prompt";
    }
    
    $prompt .= ". The model should be photorealistic, detailed, and suitable for automotive visualization.";
    
    return $prompt;
}

// Function to simulate 3D model generation
function generate3DModel($prompt, $car_id) {
    // In a real implementation, this would call an AI service like:
    // - OpenAI DALL-E 3
    // - Midjourney API
    // - Stable Diffusion
    // - Custom AI model trained on automotive data
    
    // For now, we'll simulate with placeholder data
    $model_id = 'model_' . $car_id . '_' . time();
    
    return [
        'id' => $model_id,
        'car_id' => $car_id,
        'prompt' => $prompt,
        'model_url' => 'models/' . $model_id . '.glb',
        'thumbnail_url' => 'models/thumbnails/' . $model_id . '.jpg',
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'generated'
    ];
}

// Get cars for selection
$cars_query = "SELECT c.*, u.username FROM cars c JOIN users u ON c.user_id = u.id WHERE c.status IN ('available', 'reserved', 'pending') ORDER BY c.created_at DESC";
$cars_result = $conn->query($cars_query);

// Get body types
$body_types = ['Sedan', 'SUV', 'Hatchback', 'Coupe', 'Convertible', 'Wagon', 'Pickup', 'Van', 'Crossover'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI 3D Vehicle Model Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .generator-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .model-preview {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .model-viewer {
            width: 100%;
            height: 400px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, #f8f9fa 25%, transparent 25%), 
                        linear-gradient(-45deg, #f8f9fa 25%, transparent 25%), 
                        linear-gradient(45deg, transparent 75%, #f8f9fa 75%), 
                        linear-gradient(-45deg, transparent 75%, #f8f9fa 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }
        .body-type-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .body-type-card:hover {
            transform: translateY(-5px);
        }
        .body-type-card.selected {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
        .ai-prompt-display {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            border-left: 4px solid #007bff;
        }
        .generation-steps {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .step.active {
            background: #e3f2fd;
            border-left: 4px solid #007bff;
        }
        .step.completed {
            background: #e8f5e8;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-cube me-2"></i>AI 3D Vehicle Model Generator
                </h2>

                <!-- Generator Container -->
                <div class="generator-container">
                    <div class="row">
                        <div class="col-md-8">
                            <h4><i class="fas fa-robot me-2"></i>Generate 3D Model with AI</h4>
                            <p class="mb-0">Select a vehicle and let AI generate a detailed 3D model based on its specifications.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <i class="fas fa-cube fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Generation Form -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-cog me-2"></i>Model Generation Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="generationForm">
                                    <input type="hidden" name="action" value="generate_model">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="car_id" class="form-label">Select Vehicle</label>
                                        <select class="form-select" id="car_id" name="car_id" required onchange="loadCarDetails()">
                                            <option value="">Choose a vehicle...</option>
                                            <?php while ($car = $cars_result->fetch_assoc()): ?>
                                                <option value="<?php echo $car['id']; ?>" 
                                                        data-brand="<?php echo htmlspecialchars($car['brand']); ?>"
                                                        data-model="<?php echo htmlspecialchars($car['model']); ?>"
                                                        data-year="<?php echo $car['year']; ?>"
                                                        data-body-type="<?php echo htmlspecialchars($car['body_type'] ?? ''); ?>"
                                                        data-color="<?php echo htmlspecialchars($car['color'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ') - ' . $car['username']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="brand" class="form-label">Brand</label>
                                            <input type="text" class="form-control" id="brand" name="brand" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="model" class="form-label">Model</label>
                                            <input type="text" class="form-control" id="model" name="model" readonly>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="year" class="form-label">Year</label>
                                            <input type="number" class="form-control" id="year" name="year" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="color" class="form-label">Color</label>
                                            <input type="text" class="form-control" id="color" name="color" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="body_type" class="form-label">Body Type</label>
                                        <div class="row">
                                            <?php foreach ($body_types as $type): ?>
                                                <div class="col-md-4 mb-2">
                                                    <div class="card body-type-card" onclick="selectBodyType('<?php echo $type; ?>')">
                                                        <div class="card-body text-center p-2">
                                                            <i class="fas fa-car fa-2x mb-1"></i>
                                                            <div class="small"><?php echo $type; ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" id="body_type" name="body_type" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="custom_prompt" class="form-label">Custom AI Prompt (Optional)</label>
                                        <textarea class="form-control" id="custom_prompt" name="custom_prompt" rows="3" 
                                                  placeholder="Add any specific requirements for the 3D model..."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-magic me-1"></i>Generate 3D Model
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Model Preview -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-eye me-2"></i>3D Model Preview</h5>
                            </div>
                            <div class="card-body">
                                <div class="model-preview">
                                    <?php if ($generated_model): ?>
                                        <div class="model-viewer">
                                            <div class="text-center">
                                                <i class="fas fa-cube fa-4x text-primary mb-3"></i>
                                                <h5>3D Model Generated!</h5>
                                                <p class="text-muted">Model ID: <?php echo htmlspecialchars($generated_model['id']); ?></p>
                                                <button class="btn btn-success" onclick="viewModel('<?php echo $generated_model['id']; ?>')">
                                                    <i class="fas fa-eye me-1"></i>View Model
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="model-viewer">
                                            <div class="text-center text-muted">
                                                <i class="fas fa-cube fa-4x mb-3"></i>
                                                <h5>No Model Generated Yet</h5>
                                                <p>Select a vehicle and generate a 3D model to see it here.</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Prompt Display -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-brain me-2"></i>AI Generation Prompt</h5>
                    </div>
                    <div class="card-body">
                        <div class="ai-prompt-display" id="aiPromptDisplay">
                            Select a vehicle to see the AI generation prompt...
                        </div>
                    </div>
                </div>

                <!-- Generation Steps -->
                <div class="generation-steps">
                    <h5><i class="fas fa-list-ol me-2"></i>Generation Process</h5>
                    <div class="step" id="step1">
                        <i class="fas fa-search me-3"></i>
                        <div>
                            <strong>1. Vehicle Analysis</strong>
                            <div class="small text-muted">Analyzing vehicle specifications and body type</div>
                        </div>
                    </div>
                    <div class="step" id="step2">
                        <i class="fas fa-brain me-3"></i>
                        <div>
                            <strong>2. AI Processing</strong>
                            <div class="small text-muted">Generating 3D model using AI algorithms</div>
                        </div>
                    </div>
                    <div class="step" id="step3">
                        <i class="fas fa-cube me-3"></i>
                        <div>
                            <strong>3. Model Rendering</strong>
                            <div class="small text-muted">Creating detailed 3D geometry and textures</div>
                        </div>
                    </div>
                    <div class="step" id="step4">
                        <i class="fas fa-check me-3"></i>
                        <div>
                            <strong>4. Quality Check</strong>
                            <div class="small text-muted">Validating model quality and accuracy</div>
                        </div>
                    </div>
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
                    <button type="button" class="btn btn-primary" onclick="downloadModel()">
                        <i class="fas fa-download me-1"></i>Download Model
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedBodyType = '';
        let currentModelId = '';

        function loadCarDetails() {
            const select = document.getElementById('car_id');
            const option = select.options[select.selectedIndex];
            
            if (option.value) {
                document.getElementById('brand').value = option.dataset.brand;
                document.getElementById('model').value = option.dataset.model;
                document.getElementById('year').value = option.dataset.year;
                document.getElementById('color').value = option.dataset.color;
                
                // Auto-select body type if available
                if (option.dataset.bodyType) {
                    selectBodyType(option.dataset.bodyType);
                }
                
                updateAIPrompt();
            }
        }

        function selectBodyType(type) {
            selectedBodyType = type;
            document.getElementById('body_type').value = type;
            
            // Update visual selection
            document.querySelectorAll('.body-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Find and select the clicked card
            document.querySelectorAll('.body-type-card').forEach(card => {
                if (card.textContent.includes(type)) {
                    card.classList.add('selected');
                }
            });
            
            updateAIPrompt();
        }

        function updateAIPrompt() {
            const brand = document.getElementById('brand').value;
            const model = document.getElementById('model').value;
            const year = document.getElementById('year').value;
            const color = document.getElementById('color').value;
            const customPrompt = document.getElementById('custom_prompt').value;
            
            if (brand && model && selectedBodyType) {
                let prompt = `Generate a detailed 3D vehicle model for: Brand: ${brand}, Model: ${model}, Year: ${year}, Body Type: ${selectedBodyType}, Color: ${color}`;
                
                if (customPrompt) {
                    prompt += `. Additional requirements: ${customPrompt}`;
                }
                
                prompt += `. The model should be photorealistic, detailed, and suitable for automotive visualization.`;
                
                document.getElementById('aiPromptDisplay').textContent = prompt;
            }
        }

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

        function downloadModel() {
            if (currentModelId) {
                // Simulate download
                const link = document.createElement('a');
                link.href = `models/${currentModelId}.glb`;
                link.download = `${currentModelId}.glb`;
                link.click();
            }
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

        // Update AI prompt when custom prompt changes
        document.getElementById('custom_prompt').addEventListener('input', updateAIPrompt);
    </script>
</body>
</html>

<?php
$conn->close();
?>
