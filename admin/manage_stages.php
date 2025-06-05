<?php
require_once '../database_connection.php';
startSecureSession();
requireAdminRole();

$conn = connectDB();
$sql = "SELECT id, stage_name, display_name, is_active FROM stages ORDER BY sequence_number";
$result = $conn->query($sql);
$stages = [];
while ($row = $result->fetch_assoc()) {
    $stages[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stages - NDA AFSB</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .stage-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .stage-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #007A33;
        }
        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .toggle-btn {
            width: 38px;
            height: 22px;
            border-radius: 12px;
            background: #eee;
            position: relative;
            border: none;
            outline: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .toggle-btn.on {
            background: #2ecc40;
        }
        .toggle-btn.off {
            background: #ff4136;
        }
        .toggle-dot {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            transition: left 0.2s;
        }
        .toggle-btn.on .toggle-dot {
            left: 19px;
        }
        .toggle-label {
            font-size: 0.95rem;
            font-weight: 500;
        }
        .back-button {
            position: fixed;
            top: 80px;
            left: 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-button:hover {
            transform: translateX(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }

        @media (max-width: 768px) {
            .back-button {
                top: 70px;
                left: 15px;
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <button class="back-button" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-arrow-left"></i>
    </button>

    <div class="container py-4">
        <h2 class="mb-4">Manage Screening Stages</h2>
        <?php foreach ($stages as $stage): ?>
            <div class="stage-card">
                <div class="stage-title"><?php echo htmlspecialchars($stage['display_name']); ?></div>
                <div class="toggle-switch">
                    <button class="toggle-btn <?php echo $stage['is_active'] ? 'on' : 'off'; ?>" onclick="toggleStage(<?php echo $stage['id']; ?>, event)">
                        <span class="toggle-dot"></span>
                    </button>
                    <span class="toggle-label"><?php echo $stage['is_active'] ? 'Enabled' : 'Locked'; ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
    function toggleStage(stageId, event) {
        event.stopPropagation();
        const button = event.target.closest('.toggle-btn');
        const originalState = button.classList.contains('on');
        
        // Add loading state
        button.disabled = true;
        button.style.opacity = '0.7';
        
        fetch('toggle_stage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'stage_id=' + stageId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                // Revert the toggle if there was an error
                button.classList.toggle('on', originalState);
                button.classList.toggle('off', !originalState);
                alert('Error: ' + (data.message || 'Failed to update stage status'));
            }
        })
        .catch(error => {
            // Revert the toggle if there was an error
            button.classList.toggle('on', originalState);
            button.classList.toggle('off', !originalState);
            console.error('Error:', error);
            alert('An error occurred while updating the stage status. Please try again.');
        })
        .finally(() => {
            // Remove loading state
            button.disabled = false;
            button.style.opacity = '1';
        });
    }
    </script>
</body>
</html> 