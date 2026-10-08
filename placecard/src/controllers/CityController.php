<?php

declare(strict_types=1);

class CityController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // GET /api/cities
    public function index(): never
    {
        $stmt = $this->db->query('SELECT id, name, country, emoji, tagline FROM pc_cities ORDER BY name');
        Response::success($stmt->fetchAll());
    }
}
