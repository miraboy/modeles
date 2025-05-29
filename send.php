<?php
$to = "test@example.com"; // n’importe quelle adresse, elle ne sera pas vraiment utilisée
$subject = "Test en local";
$message = "Ceci est un test depuis mon serveur local avec MailHog.";
$headers = "From: test@local.test";

if (mail($to, $subject, $message, $headers)) {
    echo "Message envoyé.";
} else {
    echo "Échec de l'envoi.";
}
?>
