Part 1: Query optimization

Original query:

        DB::table('users')
        ->join('form_data','form_data.user_id','=','users.id')
        ->join('form_options','form_data.option_id','=','form_options.id')
        ->where('users.tenant_id',$tenantId)
        ->where('form_options.label','like','%keyword%')
        ->select('users.name','form_options.label')
        ->paginate(50);

Problems in the current query:

    -> Each row repeats the user if multiple matching form_options exist.
    -> Every join and select must be manually defined, which increases boilerplate and reduces readability.
    -> Harder to reuse if more filters or relationships are introduced later.
    
1. Optimized version:
   
            User::where('tenant_id', $tenant_id)
            ->whereHas('formData.option', fn($q) =>
                $q->where('label', 'like', "%{$keyword}%"))
            ->with(['formData' => fn($q) =>
                $q->whereHas('option', fn($q2) =>
                    $q2->where('label', 'like', "%{$keyword}%"))
                ->with(['option' => fn($q3) =>
                    $q3->where('label', 'like', "%{$keyword}%")])
            ])
            ->select(['id','name'])
            ->orderBy('id')
            ->paginate(50);
   
    Refer to Http\Controllers\QueryOptimizationController.php

Whats improved in this version: 

    -> No duplicate rows. Users appear once with their matching options nested.
    -> Optimized eager loading to avoid N+1 query problem. 
    -> We can easily extend with more relations later if required


2. Recommended indexes:
   
    -> CREATE INDEX idx_users_tenant_id ON users(tenant_id); (since we are filtering based on the tenant)
   
    -> CREATE INDEX idx_form_data_user_id ON form_data(user_id); (foreign key)
   
    -> CREATE INDEX idx_form_data_option_id ON form_data(option_id); (foreign key)
   
    -> CREATE FULLTEXT INDEX idx_form_options_label_fulltext ON form_options(label); (since we are searching with "LIKE %keyword%" , normal B-Tree index will not work, so we have to use a fulltext based index)
   
    -> CREATE INDEX idx_form_data_user_option ON form_data(user_id, option_id); (if we always use the combination of tenant and user option then this will also optimize the performance)
   
    Refer to: database\migrations\2025_09_14_064542_add_indexes.php

4. As the data grows even with these indexes searching with "LIKE %keyword%" becomes slow
There are two options here:

    1. Use meilisearch
        -> It is lightweight, fast and easy to integrate into laravel using laravel-scout.
       
        -> It handles typo tolerance, ranking and relevance as well.
       
    3. Use elastic search
        -> It is opensource, distributed and is capable of handling billions of docs
       
        -> It has powerful querying like fuzzy, synonym filtering, aggregations

How these search engines improve the performance: 

Our search engine index will contain flat documents like this 

{
  "id": 123,
  "name": "John Doe",
  "tenant_id": 55,
  "options": ["option A", "option B", "option C"]
}

We store the docs with similar format containing user data and the options as well
When we search with an option it returns the matching user, we dont need to use any joins

Denormalized structures:

    -> As the data grows joins become slow and expensive so we will denormalize frequently searched data in a single column or table
    -> For our use case we create one more column called user.searchable_options
    and we sync this data whenever any insert or update happens
    -> We store something like this - ["option A", "option B", "option C"]
    -> While querying we use 
    
        User::where('tenant_id', $tenantId)
            ->where('searchable_options', 'LIKE', "%{$keyword}%")
            ->paginate(50);
            
    -> No joins required
    -> The only overhead is that we have to keep the data in sync

4. Caching strategies
   
There are different options for caching the data

    1. Query result caching per page
    
    $cacheKey = "user_search:{$tenant_id}:{$keyword}:page{$request->page}";
    $results = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($tenant_id, $keyword) {
    return User::where('tenant_id', $tenant_id)
            ->whereHas('formData.option', fn($q) =>
                $q->where('label', 'like', "%{$keyword}%"))
            ->with(['formData' => fn($q) =>
                $q->whereHas('option', fn($q2) =>
                    $q2->where('label', 'like', "%{$keyword}%"))
                ->with(['option' => fn($q3) =>
                    $q3->where('label', 'like', "%{$keyword}%")])
            ])
            ->select(['id','name'])
            ->orderBy('id')
            ->paginate(50); 
    });
   
    We dont need to hit the db for repeated searches but we need to choose the cache invalidation based on how often the user data or options change

    2. Caching only the ids
        ->  So for the first page we run the full query which fetches all the matching user ids
        -> We will cache these user ids so from the next page we will just slice the array based on per paze size and then only for those user ids we will run the query on users table to fetch the data

    Refer to : App\Http\Controllers\QueryOptimizationController.php

Part 2: Queue race conditions

Scenario:

We have multiple laravel jobs that update the user's balance when invoice is created, updated or deleted

For example for User 1

1.Invoice created - job InvoiceCreated is dispatched

2.Invoice updated - job InvoiceUpdated is dispatched

3.Invoice deleted - job InvoiceDeleted is dispatched

These jobs are processed by laravel workers which may run on multiple servers

-> Problem here is since workers pick the jobs asynchronously there is a chance that they do not follow any order for these.

-> If workers pick updated job first and then created job the balance would be updated incorrectly this is a race condition where concurrent operations on the same data leads to incorrect results.

1. Solutions:
   
    1. Per user queue
       
        -> Assign a queue per user so that we ensure jobs for the same user are processed sequentially.
       
        -> Workers process the jobs in FIFO order per queue so that they can still process parallely for different users.
       
        -> It is simple to implement in laravel.
       
        -> The only issue is most probably we will have many users so we have to have dynamic queue management to ensure each user jobs are assigned to a queue.

    3. Distributed lock
       
        a. Acquire a lock and ensure only one job can update the balance at a time, even if the jobs are processed in parallel
       
            Example: $lock = Cache::lock("user_balance_{$userId}", 10);
            if ($lock->get()) {
                try {
                    // safely update balance
                } finally {
                    $lock->release();
                }
            } else {
                // retry later
            }
       
        b. We can use optimistic lock as well
       
            DB::table('users')
            ->where('id', $userId)
            ->where('version', $oldVersion)
            ->update([
                'balance' => $newBalance,
                'version' => $oldVersion + 1
            ]);
            -> we will maintain one more column which is version
            -> each job, before updating the balance in the table checks the version , if it matches with the version at the time of reading, then it will update or otherwise we can retry 

        Refer to:
       
        App\Console\Commands\SimulateInvoiceEvents.php
       
        App\Console\Commands\ProcessUserStream.php
       
        App\Services\UserEventProducer.php

3. To handle multiple concurrent transactions on the same user, we use Redis Streams to ensure events are processed sequentially per user. On the database side, we wrap updates in row-level transactions (lockForUpdate) to prevent race conditions. This guarantees ordering and consistency even when concurrent actions happen.
 


Part 3 Multi tenant data isolation

1. Used a global Eloquent scope (TenantScope) to automatically filter all queries based on the logged-in user’s tenant_id.
   
    -> This ensures developers don’t accidentally forget to add tenant filtering.
   
    Example: User::all() automatically fetches only users belonging to the authenticated tenant.

    Auto-set tenant_id on creation:
   
    -> Whenever a new record (user, form data) is created, the model automatically assigns the tenant_id of the authenticated user.

    By implement this we have
   
        -> Centralized enforcement of tenant isolation.
        -> Less chance of human error in queries.
        -> Works at both query and creation levels.

    User Model
   
    -> Added tenant_id to $fillable.
   
    -> Added booted() method:
   
    -> Applies TenantScope globally.
   
    -> Uses auth()->user()->tenant_id to filter all queries automatically.

    Refer to:
   
    App\Scopes\TenantScope.php
   
    App\Models\User.php

3. How can we ensure tenant level isolation at controller/service layer
   
    -> We have already used global scope at application layer
   
    -> The scope automatically applies the filter and fetches the data based on the tenant id of the loggedin user
   
    -> When creating a new record also , tenant id is automatically set based on the loggedin user

5. How can we test tenant data leaks
   
    -> Ensure that users can only access data belonging to their own tenant and that new records are correctly assigned to the tenant.
   
    How the test works:
   
        -> Simulate a logged-in user for a specific tenant.

        -> Fetch data (User::all()) and assert that all returned rows have the same tenant_id as the logged-in user.

        -> Create a new user and assert that the tenant_id is automatically set to the logged-in user’s tenant.
    
    Refer to: test\Feature\TenantIsolationTest.php


Part 4 Fast dashboard aggregation

1. Refer to App\Services\DashboardService.php

    -> Wrote queries to generate the required data on the dashboard
   
    -> Used aggregations to efficiently fetch the data

3. Caching strategy

    -> Implemented Redis caching using Cache::remember() in the DashboardService.

    -> Each branch’s dashboard is cached with a key like dashboard:branch:<branchId> for 5 minutes, to avoid recalculating metrics on every request.

    The cache can be cleared selectively when data changes using Cache::forget()

    Each branch has its own cache key and queries are filtered by branch_id, ensuring tenant/branch isolation.

    Used database-level aggregations like SUM, COUNT, GROUP BY to ensure efficient queries even with large volumes of data.

    Also we are using a scheduled job that runs for every minutes to precompute the metrics for all branches and stores them in redis.


4. Solution for 100+ branches
5. 
    Solution 1:
   
        -> We are already precomputing the metrics for all the branches every five minutes and caching them. 

        -> So if multiple concurrent requests for different branches come , we can serve from cache

    Solution 2:
   
        -> We can use event driven incremental updates

        -> Instead of recomputing all the metrics for every branch, we will listen to relevant event and update only the metrics of the effected branch
        
        -> We can either store this in redis or create a summary table which contains metrics for each branch

        For example:

            Event::listen(InvoiceCreated::class, function($event) {
                Cache::increment("dashboard:branch:{$event->invoice->branch_id}:total_revenue", $event->invoice->total);
            });


