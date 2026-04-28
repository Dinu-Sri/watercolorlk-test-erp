-- Fix mojibake/emoji review text for existing google_reviews rows
SET NAMES utf8mb4;
START TRANSACTION;

UPDATE google_reviews
SET review_text = '',
    owner_response = 'Thank you so much',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT25Nd1JFWlpXSFp5WVhOd2VXeFBkR2hMUWt0c2FGRRAB';

UPDATE google_reviews
SET review_text = '⭐️⭐️⭐️⭐️⭐️⭐️',
    owner_response = 'Thank You so much',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT2w5aFRuUnFWMEV5YW5vM1pFMXZOalpmTFRjelluYxAB';

UPDATE google_reviews
SET review_text = 'එක නියමයී❤️',
    owner_response = 'Thank You so much for the feedback.',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT21SSlJpMUdUbUZSYkdaTlJ6ZFdiM0p5WVMxcE1rRRAB';

UPDATE google_reviews
SET review_text = 'Purchased a set of water colors and sketch book, that I’m very happy about. Very good quality! Also very prompt and reliable service.',
    owner_response = 'Thank you so much for your feedback. It means a lot for us. Lets build a strong watercolor culture in Sri Lanka',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT21OUExYaHFiWGswYVhOeGVtMWxkakZpYzBvelVHYxAB';

UPDATE google_reviews
SET review_text = 'Excellent service and good products ! Highly recommended ❤️❤️❤️',
    owner_response = 'Thank you so much..! Happy Painting',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT25SU1RqUmZOSFZxVDJGWFJXWk9VRFk1YzE4MlRWRRAB';

UPDATE google_reviews
SET review_text = 'Good stuff and equipment',
    owner_response = 'Thanks so much! I really appreciate the feedback and hope you have a great time using your new art tools.',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT21FNFlXdGxVM2QxV0daTWFHMUZiVll0ZFcwNWVGRRAB';

UPDATE google_reviews
SET review_text = 'Exceptional customer service',
    owner_response = 'Thank You So Much 😊 …',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT2t4eVkxQTJibTVJV1dsRmFEbHdiMjlGYW1OVU5VRRAB';

UPDATE google_reviews
SET review_text = 'Good product . Highly rerecommended',
    owner_response = 'Thank you so much..! Happy Painting',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT2xObWEyaHpRamRGYTA1c1JWQlZiVTVzTFY5TGFWRRAB';

UPDATE google_reviews
SET review_text = 'Verry good and super colity fast delivering servise and verry help full',
    owner_response = 'Thank you so much..!! Happy Painting',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT25sbVIwUk5kRGsxUmxKR05tNHhlblp4ZEd0QlQyYxAB';

UPDATE google_reviews
SET review_text = 'Excellent Service ❤️',
    owner_response = 'Thank you so much',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT2s5UlduRjBUbTFqT1MxMmNISXdRVlZ4VEVOMmMwRRAB';

UPDATE google_reviews
SET review_text = 'Excellent customer service and prompt delivery. The products are reasonably priced and are of very good quality. Can easily recommend to any watercolour artists or enthusiasts.',
    owner_response = 'Thank you so much for your kind words. It means a lot',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT25kTVJURmtaSFZTTUdwUVMyRkVkWGgwTlUxTlVuYxAB';

UPDATE google_reviews
SET review_text = 'Goods were carefully packed and received on time good customer service',
    owner_response = 'Thank You so much for you comment. 😊 …',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT2tGT1ZWZzJUblJtZUU0MFpFZzJNazVJVnpSM2JrRRAB';

UPDATE google_reviews
SET review_text = 'Excellent customer service — it truly made my day. The products are absolutely good quality for reasonable prices, and delivery was right on time. The website could be improved with more regular updates and better user-friendliness. Overall, a very positive experience with great service and products.',
    owner_response = 'Thank you for your kind feedback. We’re glad you enjoyed our products and service. We appreciate your suggestion about the website and are actively working on improvements. Looking forward to serving you again soon!',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT21FMU4yRkNiRXBRVFdSSGJFNDRiM0kxZFZaRFIyYxAB';

UPDATE google_reviews
SET review_text = 'Friendly customer service. Quick delivery. Materials were carefully packed.',
    owner_response = 'Thank you so much for your feedback. Happy painting',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT2kxNmFFRkpkSGMwTlZobmExRlBTblJ4ZG05RGRYYxAB';

UPDATE google_reviews
SET review_text = 'Good service and have quality products, Excellent customer service',
    owner_response = 'Thank you so much..! Means a lot for us. Happy Painting',
    updated_at = NOW()
WHERE review_id = 'Ci9DQUlRQUNvZENodHljRjlvT25GWVEyNXplSGRoUTBaYVpYWmZRekJDYTJFMU4wRRAB';

COMMIT;
