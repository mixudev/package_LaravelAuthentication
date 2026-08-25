<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication</title>
</head>
<body>
    <form method="POST" action="{{ route('login.perform') }}">
        @csrf
        <div>
            <label for="identifier">Identifier / Email / Username</label>
            <input id="identifier" name="identifier" type="text" required autofocus>
        </div>
        <div>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
        </div>
        <div>
            <label for="remember">
                <input id="remember" name="remember" type="checkbox" value="1">
                Remember Me
            </label>
        </div>
        <button type="submit">Sign In</button>
    </form>
</body>
</html>
