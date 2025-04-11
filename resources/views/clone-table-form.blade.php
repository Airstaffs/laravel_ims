<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <!-- Bootstrap CSS (you can use a CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Register</div>

                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ url('/clone-table') }}">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="table_suffix">Clone table suffix:</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Company</span>
                                    </div>
                                    <input type="text" name="table_suffix" id="table_suffix" class="form-control" placeholder="please enter company name" required>
                                </div>
                                <small class="form-text text-muted">[Required] please enter company name</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="email">Email/Username:</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">@</span>
                                    </div>
                                    <input type="text" name="email" id="email" class="form-control" placeholder="Enter your email or username" required>
                                </div>
                                <small class="form-text text-muted">[Required] Enter a valid email address</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="password">Password:</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    </div>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                                </div>
                                <small class="form-text text-muted">[Required] Minimum 8 characters</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>