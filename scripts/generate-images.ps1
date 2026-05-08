Add-Type -AssemblyName System.Drawing
$products = @(
    @{Name='NVIDIA GeForce RTX 5090'; File='rtx5090.png'},
    @{Name='AMD Ryzen 9 9950X'; File='ryzen9950x.png'},
    @{Name='AMD Radeon RX 9070 XT'; File='rx9070xt.png'},
    @{Name='G.Skill Trident Z5 RGB DDR5 64 Go'; File='gskill-ddr5.png'},
    @{Name='Samsung 990 Pro 2 To NVMe'; File='samsung990pro.png'},
    @{Name='Logitech G Pro X Superlight 2'; File='gpro-superlight.png'},
    @{Name='Corsair iCUE H150i ELITE CAPELLIX XT'; File='corsair-h150i.png'},
    @{Name='Intel Core Ultra 9 285K'; File='i9-285k.png'},
    @{Name='SteelSeries Arctis Nova Pro Wireless'; File='arctis-nova.png'},
    @{Name='WD Black SN850X 4 To'; File='wd-sn850x.png'},
    @{Name='Default Product Image'; File='default.png'}
)

$width = 400
$height = 300
$folder = Resolve-Path "$PSScriptRoot\..\assets\images\products"

foreach ($product in $products) {
    $bmp = New-Object System.Drawing.Bitmap $width, $height
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.Clear([System.Drawing.Color]::FromArgb(18, 27, 44))

    $rect = New-Object System.Drawing.Rectangle 0, 0, $width, $height
    $bgBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush($rect, [System.Drawing.Color]::FromArgb(10, 40, 100), [System.Drawing.Color]::FromArgb(6, 12, 22), [System.Drawing.Drawing2D.LinearGradientMode]::ForwardDiagonal)
    $g.FillRectangle($bgBrush, $rect)

    $accentRect = New-Object System.Drawing.Rectangle 0, 210, $width, 90
    $accentBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush($accentRect, [System.Drawing.Color]::FromArgb(0, 153, 255), [System.Drawing.Color]::FromArgb(122, 43, 245), [System.Drawing.Drawing2D.LinearGradientMode]::Horizontal)
    $g.FillRectangle($accentBrush, $accentRect)

    $pen = New-Object System.Drawing.Pen ([System.Drawing.Color]::FromArgb(255, 255, 255)), 4
    $g.DrawRectangle($pen, 8, 8, $width - 17, $height - 17)

    $font = [System.Drawing.Font]::new('Segoe UI', 16, [System.Drawing.FontStyle]::Bold)
    $textBrush = New-Object System.Drawing.SolidBrush [System.Drawing.Color]::White
    $shadowBrush = New-Object System.Drawing.SolidBrush [System.Drawing.Color]::FromArgb(160, 0, 0, 0)

    $format = New-Object System.Drawing.StringFormat
    $format.Alignment = [System.Drawing.StringAlignment]::Center
    $format.LineAlignment = [System.Drawing.StringAlignment]::Center

    $textRect = New-Object System.Drawing.RectangleF 20, 20, $width - 40, 170
    $g.DrawString($product.Name, $font, $shadowBrush, [System.Drawing.PointF]::new(22, 22))
    $g.DrawString($product.Name, $font, $textBrush, $textRect, $format)

    $file = Join-Path $folder $product.File
    $bmp.Save($file, [System.Drawing.Imaging.ImageFormat]::Png)
    Write-Host "Created $file"

    $g.Dispose()
    $bmp.Dispose()
}
