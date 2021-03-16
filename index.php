<?php


/**
 * Trait InstancesArrayBuildable
 */
trait InstancesArrayBuildable
{
    /**
     * @param array $data
     * @return array
     */
    public static function createInstancesCollection(array $data): array
    {
        $result = [];
        if ($data) {
            foreach ($data as $item) {
                $object = new self();
                foreach ($item as $key => $value) {
                    $object->{$key} = $value;
                }
                $result[] = $object;
            }
        }

        return $result;
    }
}

/**
 * Trait MockAPIConnectable
 */
trait MockAPIConnectable
{
    /**
     * Do a request and return decoded response
     * @return array
     */
    public static function getAPIContent(): array
    {
        try {

            $response = ApiConnector::getResponse(self::API_IDENT);

            return $response ? json_decode($response, true) : [];

        } catch (Exception $e) {
            echo sprintf('Get and decode data exception: %s', $e->getMessage());
        }
    }
}

/**
 * Class ApiConnector
 */
class ApiConnector
{
    const API_URI = 'https://5f27781bf5d27e001612e057.mockapi.io/webprovise/';

    /**
     * @param string $resource
     * @return string
     */
    public static function getResponse(string $resource): string
    {
        return file_get_contents(self::API_URI.$resource);
    }
}

/**
 * Class Travel
 */
class Travel
{
    use InstancesArrayBuildable, MockAPIConnectable;

    const API_IDENT = 'travels';

    private array $collection = [];

    # Define the properties required for calculations only
    /**
     * @param float
     */
    private float $price;

    /**
     * @param string
     */
    private string $companyId;

    /**
     * @param array $travels
     * @return array
     */
    public static function calcTotalTravelsCost(array $travels): array
    {
        $total = [];
        foreach ($travels as $travel) {
            $total[$travel->companyId] += $travel->price;
        }

        return $total;
    }
}

/**
 * Class Company
 */
class Company
{
    use InstancesArrayBuildable, MockAPIConnectable;

    const API_IDENT = 'companies';

    /**
     * @var array
     */
    private array $children = [];

    /**
     * @param $companies
     * @param $travels
     * @return array
     */
    public static function mapTravelsCost($companies, $travels): array
    {
        $result = [];
        foreach ($companies as $company) {
            $result[] = [
                'id' => $company->id,
                'name' => $company->name,
                'price' => $travels[$company->id],
                'parentId' => $company->parentId,
            ];
        }

        return $result;
    }

    /**
     * @param array $companies
     * @param int $parentId
     * @return array
     */
    public static function createCompaniesTree(array &$companies, $parentId = 0): array
    {
        $branch = [];

        foreach ($companies as $company) {
            if ($parentId == $company['parentId']) {
                $company['children'] = [];
                if ($children = self::createCompaniesTree($companies, $company['id'])) {
                    $company['children'] = $children;
                }
                $company['cost'] = self::getBranchTotalCost($company);
                $branch[] = $company;
            }
        }

        return $branch;
    }

    /**
     * @param $array
     * @param int $total
     * @param int $clear
     * @return int|mixed
     */
    public static function getBranchTotalCost(&$array, &$total = 0, $clear = 0)
    {
        if (isset($array['price'])) {
            $total += $array['price'];
            $clear = 1;
        }
        foreach ($array as $key => $data) {
            if (isset($data['price'])) {
                $total += $data['price'];
            }
            if ('children' === $key && is_array($data)) {
                self::getBranchTotalCost($data, $total, $clear);
            }
        }

        return $total;
    }

    /**
     * @param $companies
     */
    public static function clearKeys(&$companies)
    {
        self::recursiveUnsetKey($companies, 'price');
        self::recursiveUnsetKey($companies, 'parentId');
        self::recursiveMoveKey($companies, 'children');
    }

    /**
     * @param $array
     * @param $unwanted_key
     */
    public static function recursiveUnsetKey(&$array, string $key)
    {
        unset($array[$key]);
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::recursiveUnsetKey($value, $key);
            }
        }
    }

    /**
     * change array keys sequence, move a children array to the end
     * @param $array
     * @param string $key
     */
    public static function recursiveMoveKey(&$array, string $key = 'children')
    {
        foreach ($array as &$value) {
            if (array_key_exists($key, $value)) {
                $content = $value[$key];
                unset($value[$key]);
                $value[$key] = $content;
            }
            if (is_array($value)) {
                self::recursiveMoveKey($value, $key);
            }
        }
    }
}

/**
 * Class TestScript
 */
class TestScript
{
    public function execute()
    {
        $start = microtime(true);

        $companies = Company::getAPIContent();
        $travels = Travel::getAPIContent();

        # it can be realized with an instantiated objects and collection of instances properties
        $travels = Travel::createInstancesCollection($travels);
        $companies = Company::createInstancesCollection($companies);

        $totalTravelsCost = Travel::calcTotalTravelsCost($travels);
        $mappedCostsWithCompanies = Company::mapTravelsCost($companies, $totalTravelsCost);

        $result = Company::createCompaniesTree($mappedCostsWithCompanies);

        Company::clearKeys($result);

        echo '<pre>';
        print_r($result);
        echo '</pre>';

        echo 'Total time: '.(microtime(true) - $start);
    }
}

(new TestScript())->execute();
