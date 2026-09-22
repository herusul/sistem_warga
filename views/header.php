<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Warga</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="../../assets/img/favicon.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            --success-gradient: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
            --info-gradient: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
            --warning-gradient: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
            --danger-gradient: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
            --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        body { 
            background-color: #f8f9fc; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #5a5c69;
        }

        /* Premium Card Style */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.25rem;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 700;
            color: #4e73df;
        }

        /* Table Styling */
        .table { color: #5a5c69; }
        .table thead th {
            background-color: #f8f9fc;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            border-top: none;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
            cursor: pointer;
        }

        /* Button Enhancements */
        .btn-primary { background: var(--primary-gradient); border: none; box-shadow: 0 4px 6px rgba(78, 115, 223, 0.2); }
        .btn-success { background: var(--success-gradient); border: none; }
        .btn-info { background: var(--info-gradient); border: none; color: white; }
        .btn-danger { background: var(--danger-gradient); border: none; }

        /* Badge Styling */
        .badge { padding: 0.5em 0.8em; border-radius: 6px; font-weight: 600; }
        
        /* Gradient Utilities */
        .bg-gradient-primary { background: var(--primary-gradient) !important; color: white !important; }
        .bg-gradient-success { background: var(--success-gradient) !important; color: white !important; }
        .bg-gradient-info { background: var(--info-gradient) !important; color: white !important; }
        .bg-gradient-warning { background: var(--warning-gradient) !important; color: white !important; }
        .bg-gradient-danger { background: var(--danger-gradient) !important; color: white !important; }

        /* Icon Circle Styling */
        .icon-circle {
            height: 3rem;
            width: 3rem;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.2);
            font-size: 1.5rem;
        }

        /* Form Styling */
        .form-select, .form-control {
            border-radius: 10px;
            padding: 0.6rem 1rem;
            border: 1px solid #d1d3e2;
        }
        .form-select:focus, .form-control:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.1);
        }
    </style>
</head>
<body>
