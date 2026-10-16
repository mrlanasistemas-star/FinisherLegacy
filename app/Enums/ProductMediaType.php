<?php

namespace App\Enums;

enum ProductMediaType: string
{
    case Image = 'image';
    case Video = 'video';
    /** GLB/GLTF model for the product page 3D viewer. */
    case Model3d = 'model_3d';
}
