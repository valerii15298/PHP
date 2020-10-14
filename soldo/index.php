<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Soldo</title>
    <script defer src="/soldo.js"></script>
    <style>
        label {
            display: block;
            padding-top: 2%;
            padding-bottom: 0.2%;
        }

        form {
            text-align: center;
        }

        input[type=submit] {
            margin: 5%;
        }
    </style>
</head>
<body>
<form enctype="multipart/form-data" method="post" id="myform">
    <label for="owner_id">Owner</label>
    <select id="owner_id">
        <?php
        require_once 'list-users.php';
        ?>
    </select>

    <label for="wallet_id">Wallet</label>
    <select id="wallet_id">
    </select>

    <label for="pages">Page</label>
    <input type="number" id="pages" min="1" max="1" value="1">


    <br>
    <br>
    <button id="cvv">CVV</button>
    <button id="card_number">Card number</button>
    <button id="download">Download</button>

    <div id="cards">
    </div>
</form>
</body>
</html>
