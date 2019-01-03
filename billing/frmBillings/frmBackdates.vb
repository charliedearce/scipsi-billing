Public Class frmBackdates
    Dim myid As Integer
    Dim status As String

    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmBackdates_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
        End If
    End Sub

    Private Sub frmBackdates_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        viewuserbackdates()
        Me.KeyPreview = True
    End Sub

    Private Sub SimpleButton2_Click(sender As Object, e As EventArgs) Handles SimpleButton2.Click
        Try
            Dim mydate As Date
            systemtime(mydate)
            Dim todate As Date = txtdate.Text
            Dim exists As Boolean = False
            For Each itm As DataGridViewRow In DataGridView1.Rows
                If (itm.Cells(0).Value = txtdate.Text) And (itm.Cells(2).Value = "Pending") Then
                    exists = True
                End If
            Next

            If txtdate.Text = "" Or txtReason.Text = "" Then
                MsgBox("Please Fill up all important fields.", vbInformation, "Information")
            ElseIf DateTime.Parse(todate.ToString("MM/dd/yyyy")) >= DateTime.Parse(mydate.ToString("MM/dd/yyyy")) Then
                MsgBox("Invalid Date, Please make sure the date its not today or tomorrow.", vbInformation, "Information")
            ElseIf exists = True Then
                MsgBox("This Date: " + txtdate.Text + " request is already waiting for action")
            Else
                Dim result As Integer = MessageBox.Show("Are you sure you want to Request a Back Date to: " + txtdate.Text + "?", "Caution!", MessageBoxButtons.YesNo)
                If result = DialogResult.Yes Then
                    requestuserbackdate()
                    viewuserbackdates()
                End If
            End If
        Catch ex As Exception
            MsgBox(ex.Message)
        End Try

       
    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        If status = "" Then
            MsgBox("Please Select Request to delete.", vbCritical, "Information")
        ElseIf status = "Pending" Then
            Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Request?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                deleterequestbackdate(myid)
                viewuserbackdates()
            End If
        Else
            MsgBox("Unable to Delete This request. Already processed.", vbCritical, "Information")
        End If

    End Sub

    Private Sub DataGridView1_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles DataGridView1.CellContentClick

    End Sub

    Private Sub DataGridView1_SelectionChanged(sender As Object, e As EventArgs) Handles DataGridView1.SelectionChanged
        On Error Resume Next
        Dim myindex As Integer = DataGridView1.CurrentRow.Index
        myid = DataGridView1.Item(4, myindex).Value
        status = DataGridView1.Item(2, myindex).Value
    End Sub
End Class