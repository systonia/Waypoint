<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->assetTags() ?>
    <?= $this->layoutAssetTags() ?>
</head>
<body <?= $this->layoutScopeAttribute() ?>>
    <main <?= $this->scopeAttribute() ?>>
        <?= $content ?>
    </main>
</body>
</html>
