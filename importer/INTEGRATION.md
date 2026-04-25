<!-- Google Reviews Section for Product Page -->

<?php
// This code snippet shows how to display Google reviews on your product page (product.php)

$reviewRepository = new Repositories\GoogleReviewRepository(appDb());

// Option 1: Get reviews with minimum rating (e.g., 4+ stars)
$topReviews = $reviewRepository->getByMinRating(4.0, 10);

// Option 2: Get all active reviews
$allReviews = $reviewRepository->getAllActive(50);

// Option 3: Get total review count
$totalCount = $reviewRepository->getCount();
?>

<section class="reviews-section">
    <h2>Customer Reviews (<?php echo $totalCount; ?>)</h2>
    
    <?php if (empty($topReviews)): ?>
        <p>No reviews yet. Be the first to review!</p>
    <?php else: ?>
        <div class="reviews-container">
            <?php foreach ($topReviews as $review): ?>
                <article class="review-card">
                    <!-- Reviewer Profile -->
                    <div class="review-header">
                        <?php if (!empty($review['profile_picture_local_path'])): ?>
                            <img 
                                src="<?php echo htmlspecialchars($review['profile_picture_local_path']); ?>" 
                                alt="<?php echo htmlspecialchars($review['author']); ?>"
                                class="review-avatar"
                                onerror="this.src='https://via.placeholder.com/36?text=User'"
                            >
                        <?php else: ?>
                            <div class="review-avatar-placeholder"></div>
                        <?php endif; ?>
                        
                        <div class="review-meta">
                            <h3 class="review-author"><?php echo htmlspecialchars($review['author']); ?></h3>
                            
                            <!-- Rating Stars -->
                            <div class="review-rating">
                                <?php 
                                $rating = (int)$review['rating'];
                                for ($i = 0; $i < 5; $i++):
                                ?>
                                    <span class="star <?php echo $i < $rating ? 'filled' : 'empty'; ?>">★</span>
                                <?php endfor; ?>
                                <span class="rating-number"><?php echo $review['rating']; ?></span>
                            </div>
                            
                            <!-- Review Date -->
                            <time class="review-date">
                                <?php 
                                if ($review['review_date']) {
                                    echo date('M d, Y', strtotime($review['review_date']));
                                }
                                ?>
                            </time>
                        </div>
                    </div>
                    
                    <!-- Review Text -->
                    <div class="review-content">
                        <p class="review-text">
                            <?php echo htmlspecialchars($review['review_text']); ?>
                        </p>
                    </div>
                    
                    <!-- Owner Response -->
                    <?php if (!empty($review['owner_response'])): ?>
                        <div class="owner-response">
                            <p class="response-label">
                                <strong>Business Owner Response:</strong>
                            </p>
                            <p class="response-text">
                                <?php echo htmlspecialchars($review['owner_response']); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- CSS Styling (add to your stylesheet or <style> block) -->
<style>
.reviews-section {
    margin: 2rem 0;
    padding: 2rem;
    background: #f9f9f9;
    border-radius: 8px;
}

.reviews-section h2 {
    margin-bottom: 2rem;
    font-size: 1.5rem;
    font-weight: 600;
}

.reviews-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.review-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

.review-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.review-header {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.review-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.review-avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.review-meta {
    flex: 1;
}

.review-author {
    margin: 0 0 0.25rem 0;
    font-size: 0.95rem;
    font-weight: 600;
}

.review-rating {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.star {
    color: #ffc107;
    font-size: 0.9rem;
}

.star.empty {
    color: #ddd;
}

.rating-number {
    font-size: 0.85rem;
    color: #666;
    font-weight: 500;
}

.review-date {
    display: block;
    font-size: 0.8rem;
    color: #999;
}

.review-content {
    margin-bottom: 1rem;
}

.review-text {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.6;
    color: #333;
}

.owner-response {
    padding: 1rem;
    background: #f0f0f0;
    border-left: 3px solid #667eea;
    border-radius: 4px;
    font-size: 0.9rem;
}

.response-label {
    margin: 0 0 0.5rem 0;
    font-size: 0.85rem;
    color: #666;
}

.response-text {
    margin: 0;
    color: #555;
    line-height: 1.5;
}

@media (max-width: 768px) {
    .reviews-container {
        grid-template-columns: 1fr;
    }
    
    .review-card {
        padding: 1rem;
    }
}
</style>
