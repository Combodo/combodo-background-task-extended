# Extension Combodo background task extended

The `BackgroundTaskEx` class is used to manage extended background tasks, allowing you to execute a list of actions, split execution into chunks if it takes too long, and handle errors with retry attempts.

**How to use it:**
1. **Implement the `iScheduledProcess` interface**  
   This allows the task to be handled by the cron.

1. **Create a class that extends `BackgroundTaskEx`**  
   This class will define the logic of the background task, for example the list of actions to execute.

1. **Create actions by extending `BackgroundTaskExAction`**  
   Each action represents a step or operation to execute in the task.


**Simplified example:**



```php
// MyTask.php
class MyTask extends BackgroundTaskEx {
    protected function GetCurrentAction() {
        return [
            new MyAction(),
            // ... other actions
        ];
    }
}
```

```php
// MyAction.php
class MyAction extends BackgroundTaskExAction {
    public function ExecuteAction() {
        // Action logic
    }
}
```

The `BackgroundTaskExService` class manages the execution of extended background tasks in iTop. It:

- Defines a maximum execution time to avoid timeouts.
- Uses a mutex to prevent parallel execution of tasks.
- Iterates through tasks by their status (`starting`, `recovering`, `running`, etc.) and type (`interactive`, `cron`).
- For each task, it executes the associated actions, handles errors, retries, and deletes the task if it is finished.
- Allows checking if the execution time is exceeded and adjusts the limit.


The `BackgroundTaskExAction` class:

- **InitActionParams**: Initializes the action and saves specific data. Returns `true` if the action can continue. Used to set up any parameters before execution.

- **ChangeActionParamsOnError**: Modifies the action's parameters if an error occurs, to allow a retry. Returns `true` if the action can continue after the change.

- **ExecuteAction**: Executes the action using the current parameters. Receives the end execution time as a parameter. Returns `true` if the action is finished, or `false` if it needs to pause (for example, if the execution timeout is reached).

These methods allow each action to be initialized, retried on error, and executed in a controlled way within a background task.


The `BackgroundTaskEx` class:

- **GetCurrentAction**: Returns the current action object (`BackgroundTaskExAction`) associated with this task, or `null` if none is set.

- **GetNextAction**: Finds the next action to execute for this task (ordered by rank), sets it as the current action, updates the task, and returns the action object.

These methods help manage the sequence of actions within an extended background task.