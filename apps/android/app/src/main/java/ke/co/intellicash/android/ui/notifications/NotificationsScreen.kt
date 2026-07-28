package ke.co.intellicash.android.ui.notifications

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import ke.co.intellicash.android.data.model.Notification
import ke.co.intellicash.android.data.repository.ApiResult
import ke.co.intellicash.android.data.repository.IntelliCashRepository
import ke.co.intellicash.android.ui.components.ErrorScreen
import ke.co.intellicash.android.ui.components.LoadingScreen
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class NotificationsUiState {
    data object Loading : NotificationsUiState()
    data class Loaded(val notifications: List<Notification>) : NotificationsUiState()
    data class Error(val message: String) : NotificationsUiState()
}

@HiltViewModel
class NotificationsViewModel @Inject constructor(
    private val repository: IntelliCashRepository
) : ViewModel() {
    private val _state = MutableStateFlow<NotificationsUiState>(NotificationsUiState.Loading)
    val state = _state.asStateFlow()

    init { load() }

    fun load() {
        viewModelScope.launch {
            _state.value = NotificationsUiState.Loading
            when (val result = repository.getNotifications()) {
                is ApiResult.Success -> _state.value = NotificationsUiState.Loaded(result.data)
                is ApiResult.Error -> _state.value = NotificationsUiState.Error(result.message)
            }
        }
    }

    fun markRead(id: String) {
        viewModelScope.launch {
            repository.markNotificationRead(id)
            load()
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsScreen(
    onBack: () -> Unit,
    viewModel: NotificationsViewModel = hiltViewModel()
) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Notifications") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                }
            )
        }
    ) { padding ->
        when (val s = state) {
            is NotificationsUiState.Loading -> LoadingScreen(modifier = Modifier.padding(padding))
            is NotificationsUiState.Error -> ErrorScreen(s.message, viewModel::load, Modifier.padding(padding))
            is NotificationsUiState.Loaded -> {
                if (s.notifications.isEmpty()) {
                    Box(modifier = Modifier.padding(padding).fillMaxSize().padding(24.dp)) {
                        Text("No notifications", style = MaterialTheme.typography.bodyLarge)
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier.padding(padding),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        items(s.notifications, key = { it.id }) { notification ->
                            NotificationItem(
                                notification = notification,
                                onMarkRead = { viewModel.markRead(notification.id) }
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun NotificationItem(notification: Notification, onMarkRead: () -> Unit) {
    val isUnread = notification.readAt == null
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = if (isUnread) MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.3f)
            else MaterialTheme.colorScheme.surface
        )
    ) {
        ListItem(
            headlineContent = {
                Text(
                    notification.title,
                    fontWeight = if (isUnread) FontWeight.Bold else FontWeight.Normal
                )
            },
            supportingContent = {
                Text(notification.body, maxLines = 2, overflow = TextOverflow.Ellipsis)
            },
            leadingContent = {
                Icon(
                    when (notification.type) {
                        "WARNING" -> Icons.Default.Warning
                        "ERROR" -> Icons.Default.Error
                        "SUCCESS" -> Icons.Default.CheckCircle
                        else -> Icons.Default.Info
                    },
                    contentDescription = null,
                    tint = when (notification.type) {
                        "WARNING" -> MaterialTheme.colorScheme.secondary
                        "ERROR" -> MaterialTheme.colorScheme.error
                        "SUCCESS" -> MaterialTheme.colorScheme.primary
                        else -> MaterialTheme.colorScheme.tertiary
                    }
                )
            },
            trailingContent = {
                if (isUnread) {
                    IconButton(onClick = onMarkRead) {
                        Icon(Icons.Default.MarkEmailRead, contentDescription = "Mark read")
                    }
                }
            }
        )
    }
}
