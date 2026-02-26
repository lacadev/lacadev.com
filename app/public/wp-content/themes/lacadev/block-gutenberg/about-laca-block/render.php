<?php
$content = !empty($attributes['content']) ? $attributes['content'] : '';
$bg_image_url = !empty($attributes['bgImageUrl']) ? $attributes['bgImageUrl'] : '';
$class_name = 'block-about-laca';
if (!empty($attributes['className'])) {
    $class_name .= ' ' . $attributes['className'];
}

// Lấy ID ảnh nếu có để dùng srcset
$bg_image_id = !empty($attributes['bgImageId']) ? $attributes['bgImageId'] : 0;
$has_image = !empty($bg_image_url);
?>

<section class="<?php echo esc_attr($class_name); ?>" id="about-laca-hero">
    <div class="img-container">
        <?php if ($has_image) : ?>
            <div class="parallax-bg" style="background-image: url('<?php echo esc_url($bg_image_url); ?>');"></div>
        <?php else : ?>
            <?php /* === ENHANCED NATURAL CAMPFIRE SCENE === */ ?>
            <div class="about-laca-placeholder" aria-hidden="true">
                <style>
                    .about-laca-placeholder {
                        position: absolute;
                        inset: 0;
                        background: radial-gradient(circle at 50% 40%, #1a2a4e 0%, #0d0d21 60%, #05050a 100%);
                        overflow: hidden;
                        z-index: 0;
                    }

                    /* Stars - Maximum Visibility */
                    .alp-stars { 
                        position: absolute; 
                        inset: 0; 
                        z-index: 2; /* Moved above moon shadow but below moon body if needed */
                        pointer-events: none; 
                    }
                    .alp-star {
                        position: absolute;
                        border-radius: 50%;
                        background: #ffffff;
                        box-shadow: 0 0 4px 1px rgba(255, 255, 255, 0.8);
                        animation: alpTwinkle var(--d, 3s) ease-in-out infinite;
                        opacity: 0.15;
                    }
                    @keyframes alpTwinkle {
                        0%, 100% { opacity: 0.2; transform: scale(0.8); }
                        50% { opacity: 1; transform: scale(1.2); }
                    }

                    /* Moon */
                    .alp-moon {
                        position: absolute;
                        top: 10%;
                        right: 15%;
                        width: 45px;
                        height: 45px;
                        border-radius: 50%;
                        box-shadow: 8px 8px 0 0 #fef9c3;
                        filter: drop-shadow(0 0 15px rgba(254, 249, 195, 0.5));
                        transform: rotate(-10deg);
                        z-index: 3;
                    }

                    /* Ground */
                    .alp-ground {
                        position: absolute;
                        bottom: 0; left: -10%; right: -10%;
                        height: 145px;
                        background: #020204;
                        border-radius: 50% 50% 0 0;
                        z-index: 4;
                    }

                    /* Tent */
                    .alp-tent {
                        position: absolute;
                        bottom: 125px;
                        left: 32%;
                        width: 0; height: 0;
                        border-left: 65px solid transparent;
                        border-right: 65px solid transparent;
                        border-bottom: 90px solid #1a3a5f;
                        filter: drop-shadow(0 10px 25px rgba(0,0,0,0.6));
                        z-index: 5;
                    }
                    .alp-tent::after {
                        content: '';
                        position: absolute;
                        bottom: -90px; left: -22px;
                        width: 0; height: 0;
                        border-left: 22px solid transparent;
                        border-right: 22px solid transparent;
                        border-bottom: 45px solid #05080c;
                    }

                    /* Fire */
                    .alp-fire-wrap {
                        position: absolute;
                        bottom: 130px;
                        left: calc(32% + 140px);
                        width: 50px;
                        height: 50px;
                        z-index: 5;
                    }
                    
                    /* Flame Glow */
                    .alp-fire-glow {
                        position: absolute;
                        bottom: -20px; left: 50%;
                        width: 250px; height: 100px;
                        margin-left: -125px;
                        background: radial-gradient(ellipse at center, rgba(255, 100, 0, 0.3) 0%, transparent 70%);
                        animation: alpFirePulse 1.2s ease-in-out infinite alternate;
                    }
                    @keyframes alpFirePulse {
                        from { opacity: 0.4; transform: scale(0.9); }
                        to { opacity: 0.9; transform: scale(1.1); }
                    }

                    /* Flame Particles */
                    .alp-flames { position: relative; width: 100%; height: 100%; display: flex; justify-content: center; align-items: flex-end; }
                    .alp-flame {
                        position: absolute;
                        bottom: 4px;
                        width: 28px;
                        height: 50px;
                        background: #ff5e13;
                        border-radius: 50% 50% 20% 20% / 80% 80% 20% 20%;
                        filter: blur(1.5px);
                        transform-origin: bottom center;
                        animation: alpFlameMove var(--dur, 0.6s) ease-in-out infinite alternate;
                        mix-blend-mode: screen;
                    }
                    .alp-flame:nth-child(2) { width: 22px; height: 40px; background: #ffcc33; --dur: 0.5s; animation-delay: 0.1s; filter: blur(1px); }
                    .alp-flame:nth-child(3) { width: 15px; height: 25px; background: #fff; --dur: 0.4s; animation-delay: 0.2s; filter: blur(0.5px); }
                    
                    @keyframes alpFlameMove {
                        0% { transform: scale(1) rotate(-3deg) skewX(2deg); }
                        100% { transform: scale(1.1, 1.25) rotate(3deg) skewX(-2deg); }
                    }

                    /* Embers */
                    .alp-ember {
                        position: absolute;
                        bottom: 40px; left: 50%;
                        width: 3px; height: 3px;
                        background: #ffcc33;
                        border-radius: 50%;
                        filter: blur(0.5px);
                        animation: alpEmberUp var(--e-dur, 2s) linear infinite;
                    }
                    @keyframes alpEmberUp {
                        0% { transform: translate(var(--x, 0), 0) scale(1); opacity: 1; }
                        100% { transform: translate(var(--tx, 0), -120px) scale(0); opacity: 0; }
                    }

                    /* Fire Pit */
                    .alp-firepit {
                        position: absolute; bottom: 0; left: 50%;
                        transform: translateX(-50%);
                        display: flex; flex-direction: column; align-items: center;
                    }
                    .alp-logs { display: flex; gap: 4px; margin-bottom: -2px; }
                    .alp-log {
                        width: 35px; height: 8px; background: #331a0a; border-radius: 4px;
                        transform: rotate(var(--r, 20deg));
                    }
                    .alp-rocks { display: flex; gap: 2px; }
                    .alp-rock { width: 10px; height: 6px; background: #222; border-radius: 40%; }

                    /* Trees */
                    .alp-trees { position: absolute; bottom: 135px; right: 10%; display: flex; gap: 25px; z-index: 3; }
                    .alp-tree {
                        width: 0; height: 0;
                        border-left: 28px solid transparent;
                        border-right: 28px solid transparent;
                        border-bottom: 90px solid #080f08;
                    }
                    .alp-tree.small { border-left-width: 20px; border-right-width: 20px; border-bottom-width: 60px; margin-top: 30px; }

                    .alp-hint {
                        position: absolute; bottom: 30px; left: 0; right: 0;
                        text-align: center; color: rgba(255,255,255,0.2);
                        font-family: monospace; font-size: 9px;
                        letter-spacing: 4px; text-transform: uppercase;
                        z-index: 5;
                    }
                </style>

                <div class="alp-stars">
                    <?php foreach (range(1, 100) as $i) : 
                        $size = rand(15, 35) / 10; // To hơn chút: 1.5 đến 3.5px
                        $left = rand(0, 10000) / 100;
                        $top = rand(0, 8500) / 100;
                        $dur = rand(20, 50) / 10;
                        $delay = rand(0, 50) / 10;
                    ?>
                        <div class="alp-star" style="left:<?php echo $left; ?>%; top:<?php echo $top; ?>%; width:<?php echo $size; ?>px; height:<?php echo $size; ?>px; --d:<?php echo $dur; ?>s; animation-delay:<?php echo $delay; ?>s; box-shadow: 0 0 <?php echo $size + 1; ?>px #fff; opacity: 1;"></div>
                    <?php endforeach; ?>
                </div>

                <div class="alp-moon"></div>

                <div class="alp-trees">
                    <div class="alp-tree"></div>
                    <div class="alp-tree small"></div>
                </div>

                <div class="alp-tent"></div>
                
                <div class="alp-fire-wrap">
                    <div class="alp-fire-glow"></div>
                    <div class="alp-embers">
                        <?php foreach (range(1, 8) as $i) : 
                            $x = rand(-15, 15);
                            $tx = rand(-40, 40);
                            $dur = rand(15, 40) / 10;
                            $delay = rand(0, 30) / 10;
                            $left = rand(42, 58);
                        ?>
                            <div class="alp-ember" style="--x:<?php echo $x; ?>px; --tx:<?php echo $tx; ?>px; --e-dur:<?php echo $dur; ?>s; animation-delay:<?php echo $delay; ?>s; left:<?php echo $left; ?>%;"></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alp-flames">
                        <div class="alp-flame"></div>
                        <div class="alp-flame"></div>
                        <div class="alp-flame"></div>
                    </div>

                    <div class="alp-firepit">
                        <div class="alp-logs">
                            <div class="alp-log" style="--r: 25deg"></div>
                            <div class="alp-log" style="--r: -25deg; margin-left: -15px"></div>
                        </div>
                        <div class="alp-rocks">
                            <div class="alp-rock"></div>
                            <div class="alp-rock" style="margin-top: 2px"></div>
                            <div class="alp-rock"></div>
                            <div class="alp-rock" style="margin-top: 2px"></div>
                        </div>
                    </div>
                </div>

                <div class="alp-ground"></div>
                <div class="alp-hint">// Peaceful Night </div>
            </div>
        <?php endif; ?>
        
        <div class="container container--narrow">
            <div class="content-wrapper">
                <div class="about-content">
                    <?php echo nl2br(wp_kses_post($content)); ?>
                </div>
            </div>
        </div>
    </div>
</section>
