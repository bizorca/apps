<?php

declare(strict_types=1);

class RestaurantController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // GET /api/restaurants?city_id=chiang-mai
    public function index(): never
    {
        $cityId = $_GET['city_id'] ?? null;

        if (is_string($cityId) && $cityId !== '') {
            $stmt = $this->db->prepare(
                'SELECT id, name, city_id, cuisine, neighborhood, description, price_range
                 FROM pc_restaurants
                 WHERE city_id = ? AND is_active = 1
                 ORDER BY name'
            );
            $stmt->execute([$cityId]);
        } else {
            $stmt = $this->db->query(
                'SELECT id, name, city_id, cuisine, neighborhood, description, price_range
                 FROM pc_restaurants
                 WHERE is_active = 1
                 ORDER BY city_id, name'
            );
        }

        Response::success($stmt->fetchAll());
    }

    // GET /api/restaurants/{id}
    public function show(string $id): never
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, city_id, cuisine, neighborhood, description, price_range
             FROM pc_restaurants WHERE id = ? AND is_active = 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::notFound('Restaurant not found');
        }

        Response::success($row);
    }
}
