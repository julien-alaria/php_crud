<?php
session_start();
ini_set("display_errors", 0);
error_reporting(0);

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["csrf_token"];

function requireAdmin() {
    return isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin";
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=crud_phpjs;charset=utf8", "root", "1234");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $message = "";

    if ($_SERVER['REQUEST_METHOD'] === "POST") {

        if (isset($_POST["logout"])) {
            session_destroy();
            header("Location: index.php");
            exit;
        }

        if (isset($_POST["connect-email"]) && isset($_POST["connect-password"])) {
            $connectEmail = trim($_POST["connect-email"]);
            $connectPassword = $_POST["connect-password"];

            if (filter_var($connectEmail, FILTER_VALIDATE_EMAIL)) {
                $sql = "SELECT id, name, password, role FROM users WHERE email = ?";
                $stmt =$pdo->prepare($sql);
                $stmt->execute([$connectEmail]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($user && password_verify($connectPassword, $user["password"])) {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_role"] = $user["role"];

                $message = "connexion réussie.";
            } else {
                $message = "Email ou mot de passe incorrect.";
            }
        } else {
            $message = "Email invalide";
        }

        if (requireAdmin()) {

            if (!isset($_POST["csrf_token"]) || $_POST["csrf_token"] !== $_SESSION["csrf_token"]) {
                exit("Erreur : token CSRF invalide.");
            }

            if (isset($_POST["create-name"]) && isset($_POST["create-email"]) && isset($_POST["create-password"]) && isset($_POST["verif-create-password"])) {
                $name = trim($_POST["create-name"]);
                $email = trim($_POST["create-email"]);
                $filteredEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
                $password = $_POST["create-password"];
                $verifPassword = $_POST["verif-create-password"];

                if (!$filteredEmail) {
                    $message = "Email invalide";
                } elseif ($password !== $verifPassword) {
                    $message = "Veuillez entrer des mots de passe identiques.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name, $filteredEmail, $hashedPassword]);

                    $message = "Données enregistrées.";
                }
            }

            if (isset($_POST["delete-id"])) {
                $deleteId = (int) $_POST["delete-id"];

                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$deleteId]);

                $message = "Données supprimées";
            }

            if (isset($_POST["update-id"]) && isset($_POST["update-name"]) && isset($_POST["update-email"])) {
                $updateId = (int) $_POST["update-id"];
                $updateName = trim($_POST["update-name"]);
                $updateEmail = trim($_POST["update-email"]);
                
                if (!filter_var($updateEmail, FILTER_VALIDATE_EMAIL)) {
                    $message = "Email invalide";
                } else {
                    if (!empty($_POST["update-password"])) {
                        $hashedPassword = password_hash($_POST["update-password"], PASSWORD_DEFAULT);

                        $sql = "UPDATE users SET name = ?, email =?, password = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$updateName, $updateEmail, $hashedPassword, $updateId]);

                    } else {
                    
                        $sql = "UPDATE users SET name = ?, email =? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$updateName, $updateEmail, $updateId]);
                    }

                    $message = "Données mises à jour";

                }
            }
        } 
    }

    $users = [];
    if (requireAdmin()) {
        $sql = "SELECT id, name, email, role, created_at FROM users";
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
} catch (PDOException $e) {
    echo "Erreur " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css">
    <title>Crud PHP</title>
</head>
<body>

    <h1>CRUD PHP</h1>

<?php

if (!empty($message)) {
    echo '<p class="message">' . htmlspecialchars($message) . '</p>';
}

if (isset($_SESSION["user_id"])) {
    echo "Utilisateur Connecté: " . $_SESSION["user_name"] . "<br><br>Rôle: " . htmlspecialchars($_SESSION["user_role"]) . "</p>";
    echo "<form method='post'>
        <input type='submit' name='logout' value='déconnexion'>
    </form>";
} else {
    ?>
    <h2>Connexion utilisateur</h2>
    <div class="connect-form-div">
        <form action="" method="post">
            <input type="email" name="connect-email" id="connect-email" placeholder="Email" required>
            <input type="password" name="connect-password" id="connect-password" placeholder="Mote de Passe" required>
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="submit" value="Connexion">
        </form>
    </div>
    <?php
}
?>

<?php if (requireAdmin()) : ?>
    <h2>Liste des utilisateurs</h2>
    <?php foreach ($results as $result): ?>
        <div class="display-div">
            <p>
                <strong>ID:</strong> <?= htmlspecialchars($result["id"]) ?><br>
                <strong>Nom:</strong> <?= htmlspecialchars($result["name"]) ?><br>
                <strong>Email:</strong> <?= htmlspecialchars($result["email"]) ?><br>
                <strong>Role:</strong> <?= htmlspecialchars($result["role"]) ?><br>
            </p>

            <form action="" method="post">
                <input type="hidden" name="update-id" value="<?= $result["id"] ?>">
                <input type="text" name="update-name" value="<?= htmlspecialchars($result["name"]) ?>" required>
                <input type="email" name="update-email" value="<?= htmlspecialchars($result["email"]) ?>" required>
                <input type="password" name="update-password" placeholder="Nouveau mot de passe">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="submit" value="Modifier">
            </form>

            <form action="" method="post">
                <input type="hidden" name="delete-id" value="<?= $result["id"] ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="submit" value="Supprimer">
            </form>
        </div>
    <?php endforeach; ?>


    <h2>Création utilisateur</h2>
    <div class="create-form-div">
        <form action="" method="post">
            <input type="text" name="create-name" id="create-name" placeholder="Nom"  required>
            <input type="email" name="create-email" id="create-email" placeholder="Email" required>
            <input type="password" name="create-password" id="create-password" placeholder="Mot de Passe" required>
            <input type="password" name="verif-create-password" id="verif-create-password" placeholder="Verification Mot de Passe" required>
            <input type="hidden" name="csrf_token" value="<?=  $csrf_token ?>">
            <input type="submit" value="valider">
        </form>
    </div>
<?php endif; ?>
</body>
</html>
