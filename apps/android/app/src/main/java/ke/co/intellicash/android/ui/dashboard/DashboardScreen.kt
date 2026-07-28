package ke.co.intellicash.android.ui.dashboard

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import ke.co.intellicash.android.data.model.PortfolioSummary
import ke.co.intellicash.android.data.repository.ApiResult
import ke.co.intellicash.android.data.repository.IntelliCashRepository
import ke.co.intellicash.android.data.repository.TokenStore
import ke.co.intellicash.android.ui.components.ErrorScreen
import ke.co.intellicash.android.ui.components.LoadingScreen
import ke.co.intellicash.android.ui.components.StatCard
import ke.co.intellicash.android.ui.components.formatKes
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.firstOrNull
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class DashboardUiState {
    data object Loading : DashboardUiState()
    data class Loaded(val summary: PortfolioSummary, val userName: String) : DashboardUiState()
    data class Error(val message: String) : DashboardUiState()
}

@HiltViewModel
class DashboardViewModel @Inject constructor(
    private val repository: IntelliCashRepository,
    private val tokenStore: TokenStore
) : ViewModel() {
    private val _state = MutableStateFlow<DashboardUiState>(DashboardUiState.Loading)
    val state = _state.asStateFlow()

    private val _loggedOut = MutableStateFlow(false)
    val loggedOut = _loggedOut.asStateFlow()

    init {
        load()
    }

    fun load() {
        viewModelScope.launch {
            _state.value = DashboardUiState.Loading
            val userName = tokenStore.userName.firstOrNull() ?: "User"
            when (val result = repository.getPortfolioSummary()) {
                is ApiResult.Success -> _state.value = DashboardUiState.Loaded(result.data, userName)
                is ApiResult.Error -> _state.value = DashboardUiState.Error(result.message)
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            repository.logout()
            _loggedOut.value = true
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DashboardScreen(
    onNavigateToGroups: () -> Unit,
    onNavigateToNotifications: () -> Unit,
    onLogout: () -> Unit,
    viewModel: DashboardViewModel = hiltViewModel()
) {
    val state by viewModel.state.collectAsState()
    val loggedOut by viewModel.loggedOut.collectAsState()

    LaunchedEffect(loggedOut) {
        if (loggedOut) onLogout()
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("IntelliCash") },
                actions = {
                    IconButton(onClick = onNavigateToNotifications) {
                        Icon(Icons.Default.Notifications, contentDescription = "Notifications")
                    }
                    IconButton(onClick = viewModel::logout) {
                        Icon(Icons.AutoMirrored.Filled.Logout, contentDescription = "Logout")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = MaterialTheme.colorScheme.onPrimary,
                    actionIconContentColor = MaterialTheme.colorScheme.onPrimary
                )
            )
        }
    ) { padding ->
        when (val s = state) {
            is DashboardUiState.Loading -> LoadingScreen(modifier = Modifier.padding(padding))
            is DashboardUiState.Error -> ErrorScreen(
                message = s.message,
                onRetry = viewModel::load,
                modifier = Modifier.padding(padding)
            )
            is DashboardUiState.Loaded -> {
                Column(
                    modifier = Modifier
                        .padding(padding)
                        .verticalScroll(rememberScrollState())
                        .padding(16.dp)
                ) {
                    Text(
                        text = "Welcome, ${s.userName}",
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(20.dp))

                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        StatCard(
                            label = "Groups",
                            value = s.summary.groups.toString(),
                            modifier = Modifier.weight(1f)
                        )
                        StatCard(
                            label = "Members",
                            value = s.summary.members.toString(),
                            modifier = Modifier.weight(1f)
                        )
                    }
                    Spacer(modifier = Modifier.height(12.dp))

                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        StatCard(
                            label = "Active Meetings",
                            value = s.summary.activeMeetings.toString(),
                            modifier = Modifier.weight(1f)
                        )
                        StatCard(
                            label = "Total Savings",
                            value = formatKes(s.summary.totalSavingsCents),
                            modifier = Modifier.weight(1f)
                        )
                    }
                    Spacer(modifier = Modifier.height(12.dp))

                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        StatCard(
                            label = "Repayment Rate",
                            value = "${s.summary.repaymentRate.toInt()}%",
                            modifier = Modifier.weight(1f)
                        )
                        StatCard(
                            label = "Avg Credit Score",
                            value = s.summary.averageCreditScore.toInt().toString(),
                            modifier = Modifier.weight(1f)
                        )
                    }
                    Spacer(modifier = Modifier.height(24.dp))

                    Text(
                        text = "Quick Actions",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold
                    )
                    Spacer(modifier = Modifier.height(12.dp))

                    ElevatedCard(
                        onClick = onNavigateToGroups,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        ListItem(
                            headlineContent = { Text("View Groups") },
                            supportingContent = { Text("Manage savings groups, members & meetings") },
                            leadingContent = {
                                Icon(
                                    Icons.Default.Groups,
                                    contentDescription = null,
                                    tint = MaterialTheme.colorScheme.primary
                                )
                            }
                        )
                    }

                    if (s.summary.phaseDistribution.isNotEmpty()) {
                        Spacer(modifier = Modifier.height(24.dp))
                        Text(
                            text = "Group Phases",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.SemiBold
                        )
                        Spacer(modifier = Modifier.height(8.dp))

                        s.summary.phaseDistribution.forEach { (phase, count) ->
                            Row(
                                modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text(
                                    text = phase.replace("_", " "),
                                    style = MaterialTheme.typography.bodyMedium
                                )
                                Text(
                                    text = count.toString(),
                                    style = MaterialTheme.typography.bodyMedium,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}
