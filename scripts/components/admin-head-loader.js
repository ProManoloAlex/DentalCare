document.addEventListener("DOMContentLoaded", function () {
    const headContent = `
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/svg+xml" href="https://api.iconify.design/fluent-emoji-flat:tooth.svg">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
        <link rel="stylesheet" href="../styles/tablero.css">
    `;
    // Insertamos el contenido al final del head existente
    document.head.insertAdjacentHTML('beforeend', headContent);
});